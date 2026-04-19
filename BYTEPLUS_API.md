# BytePlus ModelArk — API Reference for Motions.my

> Official documentation links (open in your BytePlus-authenticated browser):
>
> | Topic | URL |
> |---|---|
> | Model list | https://docs.byteplus.com/en/docs/ModelArk/1330310 |
> | Model pricing | https://docs.byteplus.com/en/docs/ModelArk/1544106 |
> | Model release announcements | https://docs.byteplus.com/en/docs/ModelArk/1159178 |
> | Resource package rules | https://docs.byteplus.com/en/docs/ModelArk/2191775 |
> | Seedance 2.0 series tutorials | https://docs.byteplus.com/en/docs/ModelArk/2291680 |
> | General video generation tutorials | https://docs.byteplus.com/en/docs/ModelArk/2298881 |
> | Seedance 2.0 prompt guide | https://docs.byteplus.com/en/docs/ModelArk/2222480 |
> | **API — Create task** | https://docs.byteplus.com/en/docs/ModelArk/1520757 |
> | **API — Query task status** | https://docs.byteplus.com/en/docs/ModelArk/1521309 |
> | **API — Query task list** | https://docs.byteplus.com/en/docs/ModelArk/1521675 |

---

## 1. Authentication

All requests use a **Bearer token** in the `Authorization` header.

```
Authorization: Bearer <BYTEPLUS_API_KEY>
Content-Type: application/json
```

Get your API key: BytePlus Console → ModelArk → API Keys → Create API Key.

**Config constants** (`config/config.php`):

```
BYTEPLUS_API_KEY          — Bearer token
BYTEPLUS_API_URL          — https://ark.ap-southeast.bytepluses.com/api/v3
BYTEPLUS_ENDPOINT_ID      — ep-XXXXXXXX  (5s / Lite endpoint)
BYTEPLUS_ENDPOINT_ID_10S  — ep-XXXXXXXX  (10s / Pro endpoint)
```

> ⚠️ The domain is `bytepluses.com` (with an **s**), not `byteplus.com`.

---

## 2. Models (Seedance Series)

### Seedance 1.5

| Model ID | Type | Max Duration | Notes |
|---|---|---|---|
| `seedance-1-5-lite-t2v-250428` | Text-to-Video | 5s | Lite tier, faster queue |
| `seedance-1-5-pro-t2v-250528` | Text-to-Video | 10s | Pro tier, higher quality |
| `seedance-1-5-lite-i2v-250428` | Image-to-Video | 5s | First-frame continuity |
| `seedance-1-5-pro-i2v-250528` | Image-to-Video | 10s | Used for 30s Ad clips 2 & 3 |

### Seedance 2.0 (latest — see tutorials link above)

| Model ID | Type | Notes |
|---|---|---|
| `seedance-2-0-lite-t2v` | Text-to-Video | Improved motion quality |
| `seedance-2-0-pro-t2v` | Text-to-Video | Premium, cinematic output |
| `seedance-2-0-lite-i2v` | Image-to-Video | Continuation from first frame |
| `seedance-2-0-pro-i2v` | Image-to-Video | Best for 30s Ad feature |

> In this project you use the **Endpoint ID** (e.g. `ep-XXXXXXXX`) as the `model` field,
> not the model name directly. Create endpoints in:
> ModelArk Console → Online inference → Create endpoint → select model.

---

## 3. API — Create Video Task

**Reference:** https://docs.byteplus.com/en/docs/ModelArk/1520757

```
POST https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks
```

### Text-to-Video request body

```json
{
  "model": "ep-XXXXXXXX-XXXXX",
  "content": [
    {
      "type": "text",
      "text": "A luxury perfume bottle on white marble --ratio 16:9 --resolution 1080p --duration 10"
    }
  ]
}
```

### Image-to-Video request body (first-frame continuity)

```json
{
  "model": "ep-XXXXXXXX-XXXXX",
  "content": [
    {
      "type": "image_url",
      "image_url": {
        "url": "https://your-cdn.com/last-frame-of-clip1.jpg",
        "role": "first_frame"
      }
    },
    {
      "type": "image_url",
      "image_url": {
        "url": "https://your-cdn.com/hero-ending-image.jpg",
        "role": "last_frame"
      }
    },
    {
      "type": "text",
      "text": "Shot 2 scene description --ratio 16:9 --resolution 1080p --duration 10"
    }
  ]
}
```

### Prompt flags (appended to the text field)

| Flag | Values | Notes |
|---|---|---|
| `--ratio` | `16:9` `9:16` `1:1` `4:3` | Aspect ratio |
| `--resolution` | `480p` `720p` `1080p` | Output resolution |
| `--duration` | `5` `10` | Seconds; 10s requires Pro model endpoint |

### Successful response

```json
{
  "id": "13020692337823342144",
  "status": "queued",
  "model": "ep-XXXXXXXX-XXXXX",
  "created_at": 1720000000
}
```

> ⚠️ **The `id` is a large integer** (20 digits, exceeds PHP_INT_MAX).
> Always decode with `JSON_BIGINT_AS_STRING` or the task ID will be silently
> corrupted to a float (e.g. `1.3020692337823E+19`), causing every query to return 50215.
> This is already handled in `inc/byteplus.php` → `byteplus_request()`.

### Error response

```json
{
  "error": {
    "code": "InvalidModelID",
    "message": "model ep-XXXXXXXX does not exist or is inactive",
    "type": "invalid_request_error"
  }
}
```

### PHP implementation

See `inc/byteplus.php`:
- `byteplus_create_task($prompt, $resolution, $duration)` — text-to-video
- `byteplus_create_i2v_task($prompt, $firstFrameUrl, $resolution, $duration, $lastFrameUrl)` — image-to-video

---

## 4. API — Query Task Status

**Reference:** https://docs.byteplus.com/en/docs/ModelArk/1521309

```
GET https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks/{task_id}
```

### Response

```json
{
  "id": "13020692337823342144",
  "status": "succeeded",
  "model": "ep-XXXXXXXX-XXXXX",
  "created_at": 1720000000,
  "content": [
    {
      "type": "video",
      "video_url": "https://cdn.byteplus.com/xxx/output.mp4",
      "cover_image_url": "https://cdn.byteplus.com/xxx/thumb.jpg"
    }
  ],
  "usage": {
    "total_tokens": 1000,
    "completion_tokens": 1000
  }
}
```

### Status values

| BytePlus status | Normalised in this project | Meaning |
|---|---|---|
| `queued` | `queued` | Waiting in BytePlus queue |
| `running` / `processing` | `processing` | Actively generating |
| `succeeded` / `done` | `completed` | Video ready — `video_url` populated |
| `failed` / `error` | `failed` | Generation failed — credits refunded |

> The `video_url` is a **signed CDN URL** that expires after ~24 hours.
> `byteplus_url_is_expired()` and `byteplus_ensure_fresh_url()` in
> `inc/byteplus.php` handle re-fetching expired URLs.

### PHP implementation

See `byteplus_query_task(string $task_id)` in `inc/byteplus.php`.

---

## 5. API — Query Task List

**Reference:** https://docs.byteplus.com/en/docs/ModelArk/1521675

```
GET https://ark.ap-southeast.bytepluses.com/api/v3/contents/generations/tasks
    ?model=ep-XXXXXXXX
    &status=succeeded
    &page_size=20
    &page_token=<cursor>
```

### Query parameters

| Parameter | Type | Description |
|---|---|---|
| `model` | string | Filter by endpoint ID |
| `status` | string | `queued` `running` `succeeded` `failed` |
| `page_size` | int | Results per page (max 100) |
| `page_token` | string | Pagination cursor from previous response |

### Response

```json
{
  "data": [
    { "id": "...", "status": "succeeded", "content": [...] }
  ],
  "next_page_token": "abc123"
}
```

---

## 6. Project File Map

| File | Purpose |
|---|---|
| `inc/byteplus.php` | All ModelArk API calls (create, query, i2v, URL expiry) |
| `inc/long_video.php` | 30s Ad state machine — chains 3 clips with frame continuity |
| `inc/omnihuman.php` | OmniHuman avatar API (AK/SK auth via `inc/vision_auth.php`) |
| `inc/vision_auth.php` | Volcengine V4 + BytePlus HMAC256 signing |
| `client/generate.php` | Single video generation form |
| `client/long-video.php` | 30-Second Ad storyboard form |
| `client/avatar.php` | AI Avatar (OmniHuman 1.5) form |
| `cron/poll_jobs.php` | Polls all job types (video / avatar / 30s Ad) |
| `config/config.php` | API keys and endpoint IDs |

---

## 7. Seedance 2.0 Prompt Guide (summary)

> Full guide: https://docs.byteplus.com/en/docs/ModelArk/2222480

### Structure
```
[Subject] [Action/Motion] [Environment] [Camera] [Style/Mood]
```

### Good example
```
A barista pours steaming latte art into a ceramic mug, close-up shot,
café with warm bokeh background, slow pan right, cinematic warm tones
--ratio 16:9 --resolution 1080p --duration 10
```

### Tips
- Be specific about **camera movement**: `slow push in`, `pan left`, `tracking shot`, `aerial drone`
- Specify **lighting**: `golden hour`, `soft studio light`, `neon glow`
- Include **brand cues**: `product on clean white surface`, `logo visible in corner`
- Avoid negations ("no blur") — describe what you want, not what you don't
- For i2v (30s Ad clips 2 & 3), the first-frame image anchors the visual style — keep prompts consistent with the opening shot

---

## 8. Setting Up a New Endpoint (Seedance 2.0)

1. BytePlus Console → **ModelArk** → **Model activation**
2. Search for `seedance-2-0-pro-t2v` → Activate
3. Go to **Online inference** → **Create endpoint**
4. Select the activated model → Create → copy the **Endpoint ID** (`ep-XXXXXXXX`)
5. In Motions admin panel (or `config/config.php`):
   - `byteplus_endpoint_id` → your 5s endpoint ID
   - `byteplus_endpoint_id_10s` → your 10s / Pro endpoint ID
   - `byteplus_endpoint_id_i2v` → your i2v endpoint ID (for 30s Ad)

---

## 9. Common Error Codes

| Code | Meaning | Fix |
|---|---|---|
| `50215` | Input invalid for this service | Wrong req_key, inaccessible image URL, or corrupted task_id (float precision bug) |
| `50204` | req_key not supported | Service not activated in BytePlus Console |
| `50200` | General API error | Check AK/SK credentials |
| `InvalidModelID` | Endpoint doesn't exist | Create endpoint in ModelArk Console |
| `401` | Invalid API key | Check `BYTEPLUS_API_KEY` |
| `429` | Rate limit exceeded | Reduce request frequency; upgrade plan |
