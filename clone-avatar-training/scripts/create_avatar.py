#!/usr/bin/env python3
"""
Create Clone Avatar — strict implementation of the BytePlus spec at
https://docs.byteplus.com/en/docs/byteplus-vision/create-clone-avatar

Submit task:
  Endpoint : POST https://cv.byteplusapi.com/?Action=CVSubmitTask&Version=2024-06-06
  Header   : Service=cv, Region=ap-singapore-1 (auth via AK/SK)
  Body     : {
               "req_key": "realman_avatar_training_task",
               "video_url": "<public URL of the training video>",
               "interaction_optimise": <int, optional>,
               "callback_url": "<string, optional>",
               "callback_auth_info": "<string, optional>"
             }
  Response : { "code": 10000, "data": { "task_id": "<id>" }, ... }

Query task:
  Endpoint : POST https://cv.byteplusapi.com/?Action=CVGetResult&Version=2024-06-06
  Body     : { "req_key": "realman_avatar_training_task", "task_id": "<id>" }
  Response : { "code": 10000,
               "data": {
                 "status": "in_queue" | "generating" | "done" | "not_found" | "expired",
                 "resp_data": "{\"code\":0,\"msg\":\"success\",\"resource_id\":\"...\"}"
               }, ... }

This script accepts either an http(s) URL or a local file path. Local files
are published to BytePlus TOS and a pre-signed GET URL is passed as video_url.
"""

import sys
import json
import argparse
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent / "utils"))

from api_client import create_client_from_env
from tos_uploader import resolve_to_url


def create_avatar(video_url_or_path, interaction_optimise=None,
                  callback_url=None, callback_auth_info=None):
    """Train a Clone Avatar end-to-end: publish (if local) → submit → poll → resource_id."""
    try:
        video_url = resolve_to_url(video_url_or_path, kind="training-video")

        client = create_client_from_env()
        print("Submitting CVSubmitTask / realman_avatar_training_task …")

        submit = client.create_avatar(
            video_url,
            interaction_optimise=interaction_optimise,
            callback_url=callback_url,
            callback_auth_info=callback_auth_info,
        )

        if submit.get("code") != 10000:
            print(f"❌ Submit failed: {submit}")
            return None

        task_id = submit["data"]["task_id"]
        print(f"✅ Task submitted: task_id={task_id}")

        # Persist task_id immediately so we can resume polling if this run is killed
        out_dir = Path("output")
        out_dir.mkdir(exist_ok=True)
        with open(out_dir / "avatar_task.json", "w") as f:
            json.dump(
                {"task_id": task_id, "video_url": video_url, "submit_response": submit},
                f,
                indent=2,
            )
        print(f"   task_id saved → output/avatar_task.json (use scripts/check_task.py to query)")
        print("⏳ Polling CVGetResult until status=done (expect 3–6 h per doc)…")

        result = client.wait_for_task(
            client.get_avatar_result,
            task_id,
            max_wait_time=12 * 60 * 60,
            check_interval=30,
        )

        if "error" in result:
            print(f"❌ Poll failed: {result['error']}")
            return None
        if result.get("code") != 10000:
            print(f"❌ Non-success code: {result}")
            return None

        # resp_data is a JSON-encoded string per the doc
        resp_data = json.loads(result["data"]["resp_data"])
        if resp_data.get("code") != 0:
            print(f"❌ Business error: {resp_data}")
            return None

        resource_id = resp_data["resource_id"]
        print(f"✅ Training complete")
        print(f"   resource_id: {resource_id}")

        out_dir = Path("output")
        out_dir.mkdir(exist_ok=True)
        with open(out_dir / "avatar_result.json", "w") as f:
            json.dump(
                {
                    "task_id": task_id,
                    "resource_id": resource_id,
                    "resp_data": resp_data,
                    "full_response": result,
                },
                f,
                indent=2,
            )
        print(f"   saved → output/avatar_result.json")
        return resource_id

    except Exception as e:
        print(f"Error: {e}")
        return None


def main():
    parser = argparse.ArgumentParser(
        description="Create a BytePlus Clone Avatar (video_url or local path)"
    )
    parser.add_argument(
        "video",
        help="http(s) URL or local path to the training video. Local paths are auto-published to TOS.",
    )
    parser.add_argument(
        "--interaction-optimise",
        type=int,
        choices=(0, 1),
        default=None,
        help="Optional: 1 = enable live-streaming optimisation, 0 = disable",
    )
    parser.add_argument(
        "--callback-url",
        help="Optional: HTTP URL to receive task-completion POST",
    )
    parser.add_argument(
        "--callback-auth-info",
        help="Optional: auth string forwarded to the callback endpoint",
    )
    args = parser.parse_args()

    resource_id = create_avatar(
        args.video,
        interaction_optimise=args.interaction_optimise,
        callback_url=args.callback_url,
        callback_auth_info=args.callback_auth_info,
    )
    if resource_id:
        print(f"\nNext step: python scripts/generate_video.py <audio_url_or_path> --resource-id {resource_id}")
    else:
        sys.exit(1)


if __name__ == "__main__":
    main()
