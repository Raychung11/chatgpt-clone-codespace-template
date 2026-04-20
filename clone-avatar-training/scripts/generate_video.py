#!/usr/bin/env python3
"""
Generate Clone Avatar Video — strict implementation of the BytePlus spec at
https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video

Submit task:
  Endpoint : POST https://cv.byteplusapi.com/?Action=CVSubmitTask&Version=2024-06-06
  Header   : Service=cv, Region=ap-singapore-1 (auth via AK/SK)
  Body     : {
               "req_key": "realman_avatar_creation_task",
               "resource_id": "<from Create Clone Avatar>",
               "audio_url": "<public URL of the audio file>",
               "templ_start_strategy": "start_from_given_seconds"  // optional
               "templ_start_seconds": <number>,                    // optional
               "callback_url": "<string, optional>",
               "callback_auth_info": "<string, optional>"
             }
  Response : { "code": 10000, "data": { "task_id": "<id>" }, ... }

Query task:
  POST https://cv.byteplusapi.com/?Action=CVGetResult&Version=2024-06-06
  Body : { "req_key": "realman_avatar_creation_task", "task_id": "<id>" }
  Response data.resp_data is a JSON-encoded string containing the final video info.
"""

import sys
import json
import argparse
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent / "utils"))

from api_client import create_client_from_env
from tos_uploader import resolve_to_url


def generate_video(resource_id, audio_url_or_path,
                   templ_start_strategy=None, templ_start_seconds=None,
                   callback_url=None, callback_auth_info=None):
    """Submit a generation task, poll until done, return the final video URL."""
    try:
        audio_url = resolve_to_url(audio_url_or_path, kind="audio")

        client = create_client_from_env()
        print("Submitting CVSubmitTask / realman_avatar_creation_task …")
        print(f"  resource_id: {resource_id}")

        submit = client.generate_video(
            resource_id,
            audio_url,
            templ_start_strategy=templ_start_strategy,
            templ_start_seconds=templ_start_seconds,
            callback_url=callback_url,
            callback_auth_info=callback_auth_info,
        )
        if submit.get("code") != 10000:
            print(f"❌ Submit failed: {submit}")
            return None

        task_id = submit["data"]["task_id"]
        print(f"✅ Task submitted: task_id={task_id}")
        print("⏳ Polling CVGetResult until status=done …")

        result = client.wait_for_task(
            client.get_video_result,
            task_id,
            max_wait_time=60 * 60,
            check_interval=15,
        )
        if "error" in result:
            print(f"❌ Poll failed: {result['error']}")
            return None
        if result.get("code") != 10000:
            print(f"❌ Non-success code: {result}")
            return None

        data = result.get("data") or {}
        resp_data = json.loads(data.get("resp_data") or "{}")
        if resp_data.get("code") not in (None, 0):
            print(f"❌ Business error: {resp_data}")
            return None

        # Per BytePlus v2024-06-06: final URL sits at data.video_url (sibling of
        # resp_data). VideoMeta (duration/size) sits inside resp_data.vid.
        video_url = data.get("video_url") or resp_data.get("video_url")
        vid = resp_data.get("vid", {}) if isinstance(resp_data.get("vid"), dict) else {}
        meta = vid.get("VideoMeta", {})
        duration = meta.get("Duration")
        size = meta.get("Size")
        creation_duration = resp_data.get("creation_duration")

        print(f"✅ Generation complete")
        print(f"   video URL : {video_url}   (expires in ~1 h)")
        if duration: print(f"   duration  : {duration}s")
        if size:     print(f"   file size : {size / (1024 * 1024):.1f} MB")
        if creation_duration: print(f"   gen time  : {creation_duration}s")

        out_dir = Path("output")
        out_dir.mkdir(exist_ok=True)
        with open(out_dir / "video_result.json", "w") as f:
            json.dump(
                {
                    "task_id": task_id,
                    "resource_id": resource_id,
                    "video_url": video_url,
                    "duration": duration,
                    "file_size": size,
                    "creation_duration": creation_duration,
                    "full_response": result,
                },
                f,
                indent=2,
            )
        print(f"   saved → output/video_result.json")
        return video_url

    except Exception as e:
        print(f"Error: {e}")
        return None


def load_resource_id_from_avatar_result():
    try:
        with open("output/avatar_result.json", "r") as f:
            return json.load(f)["resource_id"]
    except FileNotFoundError:
        return None


def main():
    parser = argparse.ArgumentParser(
        description="Generate a BytePlus Clone Avatar video (URL or local path)"
    )
    parser.add_argument(
        "audio",
        help="http(s) URL or local audio path (wav/mp3). Local files auto-published to TOS.",
    )
    parser.add_argument(
        "--resource-id",
        help="Clone Avatar resource_id. Falls back to output/avatar_result.json if omitted.",
    )
    parser.add_argument(
        "--templ-start-strategy",
        choices=["start_from_given_seconds"],
        help="Template start strategy",
    )
    parser.add_argument(
        "--templ-start-seconds",
        type=float,
        help="Template start time in seconds (required when --templ-start-strategy is set)",
    )
    parser.add_argument("--callback-url", help="Optional: task-completion callback URL")
    parser.add_argument("--callback-auth-info", help="Optional: callback auth string")
    args = parser.parse_args()

    resource_id = args.resource_id or load_resource_id_from_avatar_result()
    if not resource_id:
        print("Provide --resource-id or run scripts/create_avatar.py first.")
        sys.exit(1)

    url = generate_video(
        resource_id,
        args.audio,
        templ_start_strategy=args.templ_start_strategy,
        templ_start_seconds=args.templ_start_seconds,
        callback_url=args.callback_url,
        callback_auth_info=args.callback_auth_info,
    )
    if not url:
        sys.exit(1)


if __name__ == "__main__":
    main()
