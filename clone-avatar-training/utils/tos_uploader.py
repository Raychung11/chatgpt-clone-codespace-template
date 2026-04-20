"""
BytePlus TOS (Object Storage) uploader.

Publishes a local file to TOS and returns a pre-signed GET URL that the
BytePlus Clone Avatar services can fetch. Falls back to the CV API keys
when dedicated TOS_* keys are not configured.

Follows the TOS Python SDK Quick Start guide for client lifecycle and
error handling (TosClientError / TosServerError, client.close()).
"""

from __future__ import annotations

import os
import uuid
from contextlib import contextmanager
from pathlib import Path
from typing import Iterator, Optional

import tos
from dotenv import load_dotenv

# Ensure TOS_* (and CV) keys are loaded regardless of who imports this module
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), "..", "config", ".env"))


def _require(name: str, fallback: Optional[str] = None) -> str:
    value = os.getenv(name) or fallback
    if not value:
        raise ValueError(
            f"{name} must be set in config/.env to publish local files to TOS"
        )
    return value


@contextmanager
def _tos_client() -> Iterator[tos.TosClientV2]:
    """Create a TosClientV2 and guarantee it is closed to release connections."""
    ak = _require("TOS_ACCESS_KEY_ID", os.getenv("ACCESS_KEY_ID"))
    sk = _require("TOS_SECRET_ACCESS_KEY", os.getenv("SECRET_ACCESS_KEY"))
    endpoint = _require("TOS_ENDPOINT")
    region = _require("TOS_REGION")

    client = tos.TosClientV2(ak, sk, endpoint, region)
    try:
        yield client
    finally:
        try:
            client.close()
        except Exception:  # best-effort, don't mask the real error
            pass


def _log_server_error(where: str, err: "tos.exceptions.TosServerError") -> None:
    print(f"❌ TOS {where} server error")
    print(f"   code       : {err.code}")
    print(f"   http code  : {err.status_code}")
    print(f"   message    : {err.message}")
    print(f"   request_id : {err.request_id}")
    print(f"   ec         : {err.ec}")
    print(f"   request_url: {err.request_url}")


def publish_file_to_tos(local_path: str | Path, object_prefix: str = "clone-avatar") -> str:
    """Upload a local file to TOS and return a pre-signed GET URL."""
    path = Path(local_path)
    if not path.is_file():
        raise FileNotFoundError(f"Not a file: {path}")

    bucket = _require("TOS_BUCKET")
    expires = int(os.getenv("TOS_URL_EXPIRY_SECONDS", "43200"))
    key = f"{object_prefix}/{uuid.uuid4().hex}/{path.name}"

    with _tos_client() as client:
        print(f"⬆️  TOS upload: {path.name} → tos://{bucket}/{key}")
        try:
            client.put_object_from_file(bucket=bucket, key=key, file_path=str(path))
        except tos.exceptions.TosClientError as e:
            print(f"❌ TOS upload client error: {e.message} (cause: {e.cause})")
            raise
        except tos.exceptions.TosServerError as e:
            _log_server_error("upload", e)
            raise

        try:
            signed = client.pre_signed_url(
                tos.HttpMethodType.Http_Method_Get,
                bucket=bucket,
                key=key,
                expires=expires,
            )
        except tos.exceptions.TosClientError as e:
            print(f"❌ TOS pre-sign client error: {e.message} (cause: {e.cause})")
            raise
        except tos.exceptions.TosServerError as e:
            _log_server_error("pre-sign", e)
            raise

    print(f"✅ Published (signed URL expires in {expires}s)")
    return signed.signed_url


def resolve_to_url(arg: str, kind: str = "file") -> str:
    """Return arg untouched if it's an http(s) URL, otherwise upload to TOS."""
    if arg.startswith(("http://", "https://")):
        return arg
    path = Path(arg)
    if not path.is_file():
        raise FileNotFoundError(
            f"'{arg}' is neither an http(s) URL nor a readable local file"
        )
    return publish_file_to_tos(path, object_prefix=f"clone-avatar/{kind}")
