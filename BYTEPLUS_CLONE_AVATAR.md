# BytePlus Clone Avatar — User Manual

Train a BytePlus Clone Avatar from a training video and render it to an MP4 with any audio. Strict implementation of the two official BytePlus docs:

- Create Clone Avatar — <https://docs.byteplus.com/en/docs/byteplus-vision/create-clone-avatar>
- Generate Clone Avatar video — <https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video>

All endpoints use Service=`cv`, Region=`ap-singapore-1`, API Version=`2024-06-06`, host=`cv.byteplusapi.com`.

---

## 1. Prerequisites

1. **BytePlus account** with Clone Avatar service subscribed and realname verification completed.
2. **AK / SK** (Access Key ID + Secret Access Key) for the account.
3. **TOS bucket** in the same BytePlus region (required only if you pass **local file paths** — the script publishes local files to TOS and hands BytePlus a pre-signed URL).
4. **ffmpeg** installed locally (for the optional video compliance check / re-encode).

> If you always pass http(s) URLs (e.g. a BytePlus CDN URL that's already public), no TOS bucket is needed — local paths are the only case that touches TOS.

---

## 2. One-time setup

```bash
cd clone-avatar-training
./setup_env.sh                     # creates venv/ and installs requirements.txt
source venv/bin/activate
cp config/.env.example config/.env # then edit config/.env
```

Fill in `config/.env`:

```
ACCESS_KEY_ID=...
SECRET_ACCESS_KEY=...
API_BASE_URL=https://cv.byteplusapi.com
SERVICE=cv
REGION=ap-singapore-1

# Only required if you plan to pass local file paths (not public URLs):
TOS_BUCKET=your-bucket-name
TOS_ENDPOINT=tos-ap-southeast-1.bytepluses.com
TOS_REGION=ap-southeast-1
# TOS_ACCESS_KEY_ID / TOS_SECRET_ACCESS_KEY can be left blank — the CV AK/SK
# above is reused when the same IAM grants TOS access.
TOS_URL_EXPIRY_SECONDS=43200
```

Verify everything wires through:

```bash
./venv/bin/python -c "
import sys; sys.path.append('utils')
from api_client import create_client_from_env, API_VERSION
c = create_client_from_env()
print('host=', c.host, 'region=', c.region, 'version=', API_VERSION)
"
```

Expected:

```
host= cv.byteplusapi.com region= ap-singapore-1 version= 2024-06-06
```

---

## 3. Training video requirements

See the official BytePlus shooting guide (linked from the Create Clone Avatar doc page) for the full list. Summary:

| Attribute       | Requirement                                     |
| --------------- | ----------------------------------------------- |
| Format          | MP4                                             |
| Duration        | 3–5 min                                         |
| Resolution      | ≥ 720p (1080p+ recommended, 4K supported)       |
| Frame rate      | ≥ 25 fps                                        |
| Bitrate         | 20–50 Mbps                                      |
| File size       | ≤ 1 GB                                          |
| Content         | Single clear-faced speaker, frontal view        |
| Motion          | Head level (±30°), steady camera, no scene cuts |
| Background      | Clean / solid / green-screen                    |

Failing any of these typically causes the training job to return business error `50215 "Input invalid for this service"` after several minutes of processing — a wasted submission.

### Pre-flight check (no API calls)

```bash
./venv/bin/python scripts/convert_video.py input/my_video.mp4 --analyze-only
```

### Re-encode to meet spec (local only)

```bash
./venv/bin/python scripts/convert_video.py input/my_video.mp4 -b 30 --fps 30 \
    -o input/my_video_ready.mp4
```

> **Note:** `convert_video.py` sets both `-b:v` (bitrate) and `-crf` (quality). In practice `-crf` wins and the output may come out under the bitrate target. If the re-analyze shows bitrate < 20 Mbps, run ffmpeg directly to force the rate:
>
> ```bash
> ffmpeg -y -i input/my_video.mp4 \
>   -c:v libx264 -b:v 30M -minrate 28M -maxrate 36M -bufsize 60M \
>   -preset medium -pix_fmt yuv420p -r 30 -c:a aac -b:a 128k \
>   input/my_video_ready.mp4
> ```

---

## 4. Train an avatar

```bash
./venv/bin/python scripts/create_avatar.py <video>
```

`<video>` can be either:

- **A public http(s) URL** — passed to BytePlus as-is. Nothing is uploaded by the script.
- **A local file path** — published to TOS first, then a pre-signed GET URL is handed to BytePlus.

Options:

| Flag                       | Purpose                                                           |
| -------------------------- | ----------------------------------------------------------------- |
| `--interaction-optimise 0` | Disable live-streaming optimisation (default is service default). |
| `--callback-url URL`       | Optional: BytePlus POSTs the task result to this URL.             |
| `--callback-auth-info STR` | Optional: forwarded to the callback endpoint for auth.            |

What happens under the hood:

1. (if local) Upload to `tos://$TOS_BUCKET/clone-avatar/training-video/<uuid>/<filename>` and mint a 12 h pre-signed URL.
2. POST `CVSubmitTask` with `req_key=realman_avatar_training_task` and the video URL.
3. Save `task_id` → `output/avatar_task.json` **immediately** (Ctrl-C safe).
4. Poll `CVGetResult` every 30 s; stream `[t+…s] status=…` lines.
5. On `status=done`, extract `resource_id` from the nested `resp_data`, save `output/avatar_result.json`.

Training runs **3–6 h** per the docs (we've seen 52 min for short compliant videos). The server-side training continues even if you kill the script — `check_task.py` can reattach any time.

---

## 5. Generate a video

```bash
./venv/bin/python scripts/generate_video.py <audio> [--resource-id UUID]
```

- `<audio>` — http(s) URL or local file path (wav/mp3). Local files are auto-published to TOS.
- `--resource-id` — defaults to the one in `output/avatar_result.json`.

Extra flags:

| Flag                         | Purpose                                                     |
| ---------------------------- | ----------------------------------------------------------- |
| `--templ-start-strategy`     | `start_from_given_seconds` to skip the beginning of the template. |
| `--templ-start-seconds N`    | Start offset in seconds (float).                            |
| `--callback-url URL`         | Webhook on completion.                                      |
| `--callback-auth-info STR`   | Auth string forwarded to the callback.                      |

Generation typically takes **15–60 s**. Result:

- `data.video_url` in the response → the final MP4 URL (valid ~1 h — download soon).
- `output/video_result.json` saved with `video_url`, `duration`, full response.

---

## 6. End-to-end in one command

```bash
./venv/bin/python examples/workflow_example.py \
    --video  input/my_video_ready.mp4 \
    --audio  input/script.wav
```

Runs Create Clone Avatar → Generate Clone Avatar Video in sequence. The same two steps as above, just chained. Use `--use-test-avatar` to skip training and generate with the BytePlus test avatar `250623-zhibo-linyunzhi`.

---

## 7. Monitoring progress

Training can take hours. Four ways to track it:

### A. Watch the running script

The foreground run prints `[t+…s] status=… | progress=…%` every 30 s when the service returns progress info.

### B. Standalone snapshot from any shell

```bash
./venv/bin/python scripts/check_task.py             # reads output/avatar_task.json
./venv/bin/python scripts/check_task.py --task-id 1234567890
./venv/bin/python scripts/check_task.py --kind video    # for a video-generation task
```

### C. Live watch in a second terminal (Ctrl-C safe)

```bash
./venv/bin/python scripts/check_task.py --watch --interval 60
```

### D. Callbacks (no polling)

Pass `--callback-url https://your-server.example.com/cb --callback-auth-info SECRET` to `create_avatar.py` / `generate_video.py`. BytePlus POSTs the final result to that URL — no need to keep the script running.

---

## 8. File / directory layout

```
clone-avatar-training/
├── config/
│   ├── .env                          # your credentials (do not commit)
│   ├── .env.example                  # template
│   └── api_config.json               # endpoint / req_key / version constants
├── scripts/
│   ├── convert_video.py              # local compliance check + re-encode
│   ├── create_avatar.py              # Create Clone Avatar
│   ├── generate_video.py             # Generate Clone Avatar video
│   └── check_task.py                 # Query any task_id at any time
├── utils/
│   ├── api_client.py                 # SDK wrapper; sets scheme/host/region/version
│   └── tos_uploader.py               # Local-file → TOS pre-signed URL helper
├── examples/
│   └── workflow_example.py           # End-to-end: video → resource_id → MP4
├── input/                            # Source training videos (gitignored)
├── output/                           # Task + result JSONs (gitignored)
│   ├── avatar_task.json              # written at submit time
│   ├── avatar_result.json            # written on status=done
│   ├── video_result.json
│   └── converted_video.mp4           # ffmpeg output (if you use convert_video.py)
├── requirements.txt
├── setup_env.sh
└── README.md                         # this file
```

---

## 9. Troubleshooting — error cheatsheet

All responses are either the **gateway envelope** (`ResponseMetadata.Error.CodeN=…`) or the **Clone Avatar business envelope** (top-level `code=…`). The envelope shape tells you which layer rejected you.

| Code   | Envelope | Meaning                          | Fix                                                                                              |
| ------ | -------- | -------------------------------- | ------------------------------------------------------------------------------------------------ |
| 100023 | gateway  | Service not entitled for account | Subscribe the Clone Avatar product, finish realname verification, confirm region.                 |
| 100010 | gateway  | SignatureDoesNotMatch            | Wrong AK/SK, clock skew, or service/region mismatch in signing.                                  |
| 50215  | business | "Input invalid for this service" | Training video failed the shooting-guide checks. Re-shoot to match the requirements in §3.        |
| 50400  | business | "Access Denied"                  | AK/SK doesn't have CV permission, or the resource_id is invalid / revoked.                       |
| 50429  | business | QPS limit exceeded               | Slow down (free tier allows 1 request at a time).                                                 |
| 50430  | business | Concurrency limit exceeded       | Another task is already in flight on your account. Wait for it to finish or cancel it.            |
| 50217  | business | Cancel not allowed               | You tried to cancel a task that already executed. No action needed.                              |
| 50500  | business | Internal Error                   | Retry.                                                                                           |
| 50501  | business | Internal RPC Error               | Retry.                                                                                           |

**Always include the `request_id` when filing a BytePlus support ticket** — it's the only way they can find the exact failure server-side.

### Common gotchas

- **Stale macOS pycryptodome wheel** → `library load disallowed by system policy`. Fix:
  ```bash
  ./venv/bin/python -m pip install --force-reinstall --no-binary :all: pycryptodome
  ```
- **Stale venv after moving the project** — `pip` shebang points to the old path. Either run `python -m pip …` (bypasses shebang) or rebuild the venv (`rm -rf venv && ./setup_env.sh`).
- **HTTP scheme error on signed request** — the SDK defaults to `http`. Our `api_client.py` overrides to `https`; if you swap in the raw SDK, call `set_scheme("https")`.
- **Wrong API version** — the volcengine SDK hard-codes Version `2022-08-31` (CN). Our `api_client.py` overrides to `2024-06-06` via `set_api_info()`. Don't bypass this.
- **Pre-signed URL can't be HEAD'd** — our signed URLs are `GET`-only. Use `curl -r 0-1023` to smoke-test reachability instead.

---

## 10. Cost notes

Per the OnePage overview (check the console for current pricing):

- **Create Clone Avatar**: USD 300 per Resource Package (one trained avatar per package).
- **Generate Clone Avatar video**: billed per second of output duration.

Free-trial accounts typically cannot create new avatars — they can only call Generate using the BytePlus-provided test avatar `250623-zhibo-linyunzhi` (pass `--use-test-avatar`).

---

## 11. Useful one-liners

```bash
# Activate venv every new shell
source venv/bin/activate

# Check compliance of a training video without uploading
./venv/bin/python scripts/convert_video.py input/x.mp4 --analyze-only

# Train from a public URL (no TOS upload)
./venv/bin/python scripts/create_avatar.py "https://cdn.example.com/me.mp4"

# Train from a local file (TOS upload happens automatically)
./venv/bin/python scripts/create_avatar.py input/me.mp4

# Reattach to a running training from another shell
./venv/bin/python scripts/check_task.py --watch --interval 60

# Generate using the latest trained avatar + a public audio URL
./venv/bin/python scripts/generate_video.py "https://cdn.example.com/audio.mp3"

# End-to-end
./venv/bin/python examples/workflow_example.py --video input/me.mp4 --audio input/script.wav
```

---

## 12. Project integration notes (Motions platform)

This Clone Avatar flow uses **different req_keys** from OmniHuman 1.5:

| Feature | req_key | Purpose |
|---|---|---|
| OmniHuman 1.5 (current) | `realman_avatar_picture_omni15_cv` | One-shot talking-head from portrait + audio |
| Clone Avatar — train | `realman_avatar_training_task` | Train a custom avatar from a 3–5 min video |
| Clone Avatar — generate | *(uses `resource_id`, not req_key)* | Render the trained avatar with any audio |

**When OmniHuman returns code 50215:** This is most commonly caused by the training video not meeting the requirements in §3 above (resolution, bitrate, duration, single face, etc.) — not a code bug. See §9 for the full error cheatsheet.

The Motions platform admin diagnostic page is at `/admin/debug_omnihuman.php` and shows the raw API response to help diagnose which layer (gateway vs business) is rejecting the request.
