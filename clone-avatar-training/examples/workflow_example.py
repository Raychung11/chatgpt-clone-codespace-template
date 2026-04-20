#!/usr/bin/env python3
"""
End-to-end workflow example: local training video + local audio → final MP4 URL.

Strict to the two BytePlus docs:
  https://docs.byteplus.com/en/docs/byteplus-vision/create-clone-avatar
  https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video

No url2vid step — the create-avatar endpoint accepts video_url directly.
Local file paths are auto-published to TOS (see utils/tos_uploader.py).
"""

import os
import sys
import json
import argparse
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent / "utils"))

from api_client import create_client_from_env
from tos_uploader import resolve_to_url


class CloneAvatarWorkflow:

    def __init__(self):
        self.client = create_client_from_env()
        self.output_dir = Path("output")
        self.output_dir.mkdir(exist_ok=True)

    def run(self, video, audio, use_test_avatar=False):
        print("🚀 BytePlus Clone Avatar workflow")
        print("=" * 50)

        audio_url = resolve_to_url(audio, kind="audio")

        if use_test_avatar:
            resource_id = os.getenv("TEST_AVATAR_RESOURCE_ID", "250623-zhibo-linyunzhi")
            print(f"🎭 Using test avatar: {resource_id}")
        else:
            print("\n🎭 Step 1: Create Clone Avatar")
            video_url = resolve_to_url(video, kind="training-video")
            resource_id = self._create_avatar(video_url)
            if not resource_id:
                return False

        print("\n🎬 Step 2: Generate Clone Avatar video")
        final_url = self._generate_video(resource_id, audio_url)
        if not final_url:
            return False

        print("\n✅ Done")
        print(f"   resource_id : {resource_id}")
        print(f"   video URL   : {final_url}")
        return True

    def _create_avatar(self, video_url):
        submit = self.client.create_avatar(video_url)
        if submit.get("code") != 10000:
            print(f"  ❌ Submit failed: {submit}")
            return None
        task_id = submit["data"]["task_id"]
        print(f"  task_id: {task_id}")
        result = self.client.wait_for_task(
            self.client.get_avatar_result, task_id,
            max_wait_time=12 * 60 * 60, check_interval=30,
        )
        if "error" in result or result.get("code") != 10000:
            print(f"  ❌ Training failed: {result}")
            return None
        resp_data = json.loads(result["data"]["resp_data"])
        if resp_data.get("code") != 0:
            print(f"  ❌ Business error: {resp_data}")
            return None
        return resp_data["resource_id"]

    def _generate_video(self, resource_id, audio_url):
        submit = self.client.generate_video(resource_id, audio_url)
        if submit.get("code") != 10000:
            print(f"  ❌ Submit failed: {submit}")
            return None
        task_id = submit["data"]["task_id"]
        print(f"  task_id: {task_id}")
        result = self.client.wait_for_task(
            self.client.get_video_result, task_id,
            max_wait_time=60 * 60, check_interval=15,
        )
        if "error" in result or result.get("code") != 10000:
            print(f"  ❌ Generation failed: {result}")
            return None
        resp_data = json.loads(result["data"]["resp_data"])
        vid = resp_data.get("vid") if isinstance(resp_data.get("vid"), dict) else {}
        return vid.get("url") or resp_data.get("video_url")


def main():
    parser = argparse.ArgumentParser(
        description="End-to-end Clone Avatar workflow (URLs or local paths)"
    )
    parser.add_argument("--video", help="Training video (http(s) URL or local path)")
    parser.add_argument("--audio", required=True, help="Audio (http(s) URL or local path)")
    parser.add_argument("--use-test-avatar", action="store_true",
                        help="Skip training and use the BytePlus test avatar")
    args = parser.parse_args()

    if not args.use_test_avatar and not args.video:
        parser.error("--video is required unless --use-test-avatar is set")

    ok = CloneAvatarWorkflow().run(args.video, args.audio, args.use_test_avatar)
    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
