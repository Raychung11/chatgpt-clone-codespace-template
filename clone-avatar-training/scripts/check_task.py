#!/usr/bin/env python3
"""
Query or watch a BytePlus Clone Avatar task without resubmitting.

Reads the most recent task_id from output/avatar_task.json by default, or
accepts --task-id explicitly. Supports both training and generation tasks
(the same CVGetResult endpoint handles both; req_key determines which).
"""

import sys
import json
import time
import argparse
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent / "utils"))

from api_client import create_client_from_env


def _load_saved(kind):
    path = Path("output") / ("avatar_task.json" if kind == "avatar" else "video_task.json")
    if not path.is_file():
        # fall back to the result files (in case a previous run completed)
        alt = Path("output") / ("avatar_result.json" if kind == "avatar" else "video_result.json")
        if alt.is_file():
            return json.loads(alt.read_text()).get("task_id")
        return None
    return json.loads(path.read_text()).get("task_id")


def snapshot(client, task_id, kind):
    fn = client.get_avatar_result if kind == "avatar" else client.get_video_result
    result = fn(task_id)
    code = result.get("code")
    if code != 10000:
        print(f"code={code}  message={result.get('message')}  request_id={result.get('request_id')}")
        return result
    data = result.get("data") or {}
    status = data.get("status")
    print(f"task_id={task_id}")
    print(f"status ={status}")
    raw = data.get("resp_data")
    if isinstance(raw, str) and raw:
        try:
            inner = json.loads(raw)
            for k in ("progress", "received_at", "started_at", "trained_at",
                      "finished_at", "training_duration", "resource_id",
                      "creation_duration", "code", "msg"):
                if k in inner:
                    print(f"  {k}: {inner[k]}")
            if "vid" in inner and isinstance(inner["vid"], dict):
                v = inner["vid"]
                if v.get("url"):
                    print(f"  video_url: {v['url']}")
        except (ValueError, TypeError):
            print(f"  resp_data (raw): {raw[:200]}")
    return result


def main():
    parser = argparse.ArgumentParser(
        description="Query or watch a BytePlus Clone Avatar task"
    )
    parser.add_argument(
        "--task-id",
        help="Task id. If omitted, read from output/avatar_task.json (or video_task.json).",
    )
    parser.add_argument(
        "--kind",
        choices=("avatar", "video"),
        default="avatar",
        help="Which task type. Determines req_key used in CVGetResult.",
    )
    parser.add_argument(
        "--watch",
        action="store_true",
        help="Keep polling every --interval seconds until status=done.",
    )
    parser.add_argument("--interval", type=int, default=30, help="Poll interval (seconds)")
    args = parser.parse_args()

    task_id = args.task_id or _load_saved(args.kind)
    if not task_id:
        print("No task_id given and no saved task id found.")
        print("Pass --task-id, or run scripts/create_avatar.py first.")
        sys.exit(1)

    client = create_client_from_env()

    if not args.watch:
        snapshot(client, task_id, args.kind)
        return

    while True:
        result = snapshot(client, task_id, args.kind)
        status = (result.get("data") or {}).get("status")
        print()
        if status == "done":
            return
        if status in ("not_found", "expired") or result.get("code") != 10000:
            sys.exit(1)
        time.sleep(args.interval)


if __name__ == "__main__":
    main()
