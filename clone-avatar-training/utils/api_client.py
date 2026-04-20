"""
BytePlus Clone Avatar API Client (BytePlus international).

Strict implementation of the two official docs:
  https://docs.byteplus.com/en/docs/byteplus-vision/create-clone-avatar
  https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video

Endpoint : https://cv.byteplusapi.com
Service  : cv
Region   : ap-singapore-1
Version  : 2024-06-06
Actions  : CVSubmitTask / CVGetResult / CVCancelTask
"""

import os
import json
import time

from volcengine.visual.VisualService import VisualService


API_VERSION = "2024-06-06"


class BytePlusAPIClient:
    """Thin wrapper around the volcengine SDK, configured for BytePlus international."""

    def __init__(self, access_key_id, secret_access_key, region, service, host):
        self.access_key_id = access_key_id
        self.secret_access_key = secret_access_key
        self.region = region
        self.service = service
        self.host = host

        self.visual_service = VisualService()
        self.visual_service.set_ak(access_key_id)
        self.visual_service.set_sk(secret_access_key)

        # Override SDK defaults (which point to Volcano Engine China).
        self.visual_service.set_host(host)
        self.visual_service.set_scheme("https")
        self.visual_service.service_info.credentials.service = service
        self.visual_service.service_info.credentials.region = region

        # Override API Version — SDK ships with 2022-08-31 (CN), BytePlus uses 2024-06-06.
        for action in ("CVSubmitTask", "CVGetResult", "CVCancelTask"):
            self.visual_service.set_api_info(action, API_VERSION)

        # Load req_key config
        config_path = os.path.join(os.path.dirname(__file__), "..", "config", "api_config.json")
        with open(config_path, "r") as f:
            self.config = json.load(f)

    # ------------------------------------------------------------------
    # Create Clone Avatar
    # https://docs.byteplus.com/en/docs/byteplus-vision/create-clone-avatar
    # ------------------------------------------------------------------

    def create_avatar(self, video_url, interaction_optimise=None,
                      callback_url=None, callback_auth_info=None):
        """Submit a clone-avatar training task (CVSubmitTask)."""
        if not isinstance(video_url, str) or not video_url:
            raise ValueError("video_url must be a non-empty string")

        body = {
            "req_key": self.config["services"]["create_avatar"]["req_key"],
            "video_url": video_url,
        }
        if interaction_optimise is not None:
            body["interaction_optimise"] = interaction_optimise
        if callback_url:
            body["callback_url"] = callback_url
        if callback_auth_info:
            body["callback_auth_info"] = callback_auth_info

        return self.visual_service.cv_submit_task(body)

    def get_avatar_result(self, task_id):
        """Query the training task (CVGetResult)."""
        body = {
            "req_key": self.config["services"]["create_avatar"]["req_key"],
            "task_id": task_id,
        }
        return self.visual_service.cv_get_result(body)

    def cancel_avatar_task(self, task_id):
        """Cancel the training task (CVCancelTask)."""
        body = {
            "req_key": self.config["services"]["create_avatar"]["req_key"],
            "task_id": task_id,
        }
        return self.visual_service.common_json_handler("CVCancelTask", body)

    # ------------------------------------------------------------------
    # Generate Clone Avatar Video
    # https://docs.byteplus.com/en/docs/byteplus-vision/generate-clone-avatar-video
    # ------------------------------------------------------------------

    def generate_video(self, resource_id, audio_url,
                       templ_start_strategy=None, templ_start_seconds=None,
                       callback_url=None, callback_auth_info=None):
        """Submit a video-generation task (CVSubmitTask)."""
        body = {
            "req_key": self.config["services"]["generate_video"]["req_key"],
            "resource_id": resource_id,
            "audio_url": audio_url,
        }
        if templ_start_strategy:
            body["templ_start_strategy"] = templ_start_strategy
        if templ_start_seconds is not None:
            body["templ_start_seconds"] = templ_start_seconds
        if callback_url:
            body["callback_url"] = callback_url
        if callback_auth_info:
            body["callback_auth_info"] = callback_auth_info

        return self.visual_service.cv_submit_task(body)

    def get_video_result(self, task_id):
        """Query the video-generation task (CVGetResult)."""
        body = {
            "req_key": self.config["services"]["generate_video"]["req_key"],
            "task_id": task_id,
        }
        return self.visual_service.cv_get_result(body)

    def cancel_video_task(self, task_id):
        """Cancel a video-generation task (CVCancelTask)."""
        body = {
            "req_key": self.config["services"]["generate_video"]["req_key"],
            "task_id": task_id,
        }
        return self.visual_service.common_json_handler("CVCancelTask", body)

    # ------------------------------------------------------------------

    def wait_for_task(self, get_result_func, task_id,
                      max_wait_time=300, check_interval=10):
        """Poll until the task reaches a terminal state.

        Surfaces `progress` and other fields from the business-level `resp_data`
        when the service includes them (e.g. 0–100 percent during `generating`).
        """
        start = time.time()
        while time.time() - start < max_wait_time:
            result = get_result_func(task_id)
            if result.get("code") != 10000:
                return result
            data = result.get("data") or {}
            status = data.get("status")
            if status == "done":
                return result
            if status in ("in_queue", "generating"):
                extra = ""
                raw = data.get("resp_data")
                if isinstance(raw, str) and raw:
                    try:
                        inner = json.loads(raw)
                        progress = inner.get("progress")
                        if progress is not None:
                            extra = f" | progress={progress}%"
                        for k in ("received_at", "started_at", "trained_at"):
                            v = inner.get(k)
                            if v:
                                extra += f" | {k}={v}"
                    except (ValueError, TypeError):
                        pass
                elapsed = int(time.time() - start)
                print(f"[t+{elapsed:>5}s] status={status}{extra}")
                time.sleep(check_interval)
                continue
            if status in ("not_found", "expired"):
                return {"error": f"Task {status}: {task_id}"}
            # Unknown status — return as-is
            return result
        return {"error": f"Task timeout after {max_wait_time}s"}


def create_client_from_env():
    """Build a BytePlusAPIClient from config/.env."""
    from dotenv import load_dotenv
    load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), "..", "config", ".env"))

    access_key_id = os.getenv("ACCESS_KEY_ID")
    secret_access_key = os.getenv("SECRET_ACCESS_KEY")
    if not access_key_id or not secret_access_key:
        raise ValueError("ACCESS_KEY_ID and SECRET_ACCESS_KEY must be set in config/.env")

    api_base_url = os.getenv("API_BASE_URL", "https://cv.byteplusapi.com")
    service = os.getenv("SERVICE", "cv")
    host = api_base_url.replace("https://", "").replace("http://", "").rstrip("/")
    default_region = "ap-singapore-1" if "byteplusapi.com" in host else "cn-north-1"
    region = os.getenv("REGION", default_region)

    return BytePlusAPIClient(access_key_id, secret_access_key, region, service, host)
