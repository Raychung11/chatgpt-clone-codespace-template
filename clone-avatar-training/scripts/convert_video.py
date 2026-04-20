#!/usr/bin/env python3
"""
Video Bitrate Converter

This script converts video files to meet BytePlus Clone Avatar requirements:
- Bitrate: 20-50 Mbps (recommended)
- Resolution: Minimum 720p, 1080p+ recommended
- Frame Rate: >25fps
- Format: MP4
- Duration: 3-5 minutes
- File Size: <1GB
"""

import os
import sys
import json
import argparse
import subprocess
from pathlib import Path


def get_video_info(video_path):
    """Get detailed video information using ffprobe"""
    try:
        cmd = [
            'ffprobe', '-v', 'quiet', '-print_format', 'json',
            '-show_format', '-show_streams', video_path
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)
        return json.loads(result.stdout)
    except subprocess.CalledProcessError as e:
        print(f"Error analyzing video: {e}")
        return None
    except FileNotFoundError:
        print("Error: ffmpeg/ffprobe not found. Please install ffmpeg first.")
        return None


def analyze_video_compliance(info):
    """Analyze if video meets BytePlus requirements"""
    if not info:
        return False, []

    issues = []
    recommendations = []

    # Get video stream
    video_stream = None
    for stream in info['streams']:
        if stream['codec_type'] == 'video':
            video_stream = stream
            break

    if not video_stream:
        return False, ["No video stream found"]

    # Check format
    format_name = info['format']['format_name']
    if 'mp4' not in format_name.lower():
        issues.append("❌ Format: Not MP4")
        recommendations.append("Convert to MP4 format")
    else:
        print("✅ Format: MP4")

    # Check duration
    duration = float(info['format']['duration'])
    if duration < 180:  # 3 minutes
        issues.append(f"⚠️ Duration: {duration:.1f}s (recommend 3-5 minutes)")
    elif duration > 300:  # 5 minutes
        issues.append(f"⚠️ Duration: {duration:.1f}s (recommend 3-5 minutes)")
    else:
        print(f"✅ Duration: {duration:.1f}s")

    # Check resolution
    width = int(video_stream['width'])
    height = int(video_stream['height'])
    min_dimension = min(width, height)

    if min_dimension < 720:
        issues.append(f"❌ Resolution: {width}×{height} (minimum 720p)")
        recommendations.append("Upscale to at least 720p")
    elif min_dimension < 1080:
        print(f"⚠️ Resolution: {width}×{height} (720p+, recommend 1080p+)")
    else:
        print(f"✅ Resolution: {width}×{height}")

    # Check frame rate
    fps_str = video_stream['r_frame_rate']
    fps = eval(fps_str)  # Convert "60/1" to 60.0

    if fps < 25:
        issues.append(f"❌ Frame Rate: {fps}fps (minimum 25fps)")
        recommendations.append("Increase frame rate to 25fps+")
    else:
        print(f"✅ Frame Rate: {fps}fps")

    # Check bitrate
    bitrate = int(video_stream.get('bit_rate', 0))
    bitrate_mbps = bitrate / 1_000_000

    if bitrate_mbps < 20:
        issues.append(f"❌ Bitrate: {bitrate_mbps:.1f}Mbps (recommend 20-50Mbps)")
        recommendations.append("Increase bitrate to 20-50Mbps range")
    elif bitrate_mbps > 50:
        issues.append(f"⚠️ Bitrate: {bitrate_mbps:.1f}Mbps (recommend 20-50Mbps)")
        recommendations.append("Consider reducing bitrate to save bandwidth")
    else:
        print(f"✅ Bitrate: {bitrate_mbps:.1f}Mbps")

    # Check file size
    file_size = int(info['format']['size'])
    file_size_mb = file_size / (1024 * 1024)
    file_size_gb = file_size_mb / 1024

    if file_size_gb > 1:
        issues.append(f"❌ File Size: {file_size_gb:.1f}GB (maximum 1GB)")
        recommendations.append("Reduce bitrate or duration to decrease file size")
    else:
        print(f"✅ File Size: {file_size_mb:.1f}MB")

    return len(issues) == 0, issues, recommendations


def convert_video(input_path, output_path, target_bitrate=30, target_fps=None,
                 target_resolution=None, audio_bitrate=128):
    """Convert video to meet BytePlus requirements"""

    print(f"\n🎬 Converting video...")
    print(f"  Input: {input_path}")
    print(f"  Output: {output_path}")
    print(f"  Target Bitrate: {target_bitrate}Mbps")

    # Build ffmpeg command
    cmd = [
        'ffmpeg', '-y',  # Overwrite output file
        '-i', str(input_path),
        '-c:v', 'libx264',  # Video codec
        '-b:v', f'{target_bitrate}M',  # Video bitrate
        '-maxrate', f'{target_bitrate * 1.2}M',  # Max bitrate (20% buffer)
        '-bufsize', f'{target_bitrate * 2}M',  # Buffer size
        '-c:a', 'aac',  # Audio codec
        '-b:a', f'{audio_bitrate}k',  # Audio bitrate
        '-preset', 'medium',  # Encoding speed/quality balance
        '-crf', '23',  # Quality (lower = better quality)
        '-pix_fmt', 'yuv420p'  # Pixel format for compatibility
    ]

    # Add frame rate if specified
    if target_fps:
        cmd.extend(['-r', str(target_fps)])
        print(f"  Target FPS: {target_fps}")

    # Add resolution if specified
    if target_resolution:
        cmd.extend(['-vf', f'scale={target_resolution}'])
        print(f"  Target Resolution: {target_resolution}")

    cmd.append(str(output_path))

    try:
        print("  🔄 Processing...")
        print("  Command:", ' '.join(cmd))

        # Run conversion
        result = subprocess.run(cmd, capture_output=True, text=True, check=True)

        print("  ✅ Conversion completed successfully!")
        return True

    except subprocess.CalledProcessError as e:
        print(f"  ❌ Conversion failed: {e}")
        print(f"  stderr: {e.stderr}")
        return False


def main():
    parser = argparse.ArgumentParser(description="Convert video for BytePlus Clone Avatar")
    parser.add_argument("input", nargs='?',
                       default="/Users/bytedance/Desktop/clone-avatar/sample.mp4",
                       help="Input video file")
    parser.add_argument("-o", "--output",
                       help="Output video file (default: output/converted_video.mp4)")
    parser.add_argument("-b", "--bitrate", type=int, default=30,
                       help="Target video bitrate in Mbps (default: 30)")
    parser.add_argument("--fps", type=int,
                       help="Target frame rate (default: keep original)")
    parser.add_argument("--resolution",
                       help="Target resolution (e.g., '1920:1080', 'iw*1.5:ih*1.5')")
    parser.add_argument("--audio-bitrate", type=int, default=128,
                       help="Audio bitrate in kbps (default: 128)")
    parser.add_argument("--analyze-only", action="store_true",
                       help="Only analyze video compliance, don't convert")

    args = parser.parse_args()

    input_path = Path(args.input)
    if not input_path.exists():
        print(f"❌ Input file not found: {input_path}")
        sys.exit(1)

    # Set default output path
    if args.output:
        output_path = Path(args.output)
    else:
        output_dir = Path("output")
        output_dir.mkdir(exist_ok=True)
        output_path = output_dir / "converted_video.mp4"

    print("🔍 Analyzing video compliance...")
    print("=" * 50)

    # Analyze current video
    info = get_video_info(input_path)
    if not info:
        sys.exit(1)

    is_compliant, issues, recommendations = analyze_video_compliance(info)

    print("\n📊 Analysis Results:")
    if is_compliant:
        print("🎉 Video meets all BytePlus requirements!")
        if not args.analyze_only:
            print("No conversion needed, but you can still optimize if desired.")
    else:
        print("Issues found:")
        for issue in issues:
            print(f"  {issue}")

        if recommendations:
            print("\nRecommendations:")
            for rec in recommendations:
                print(f"  • {rec}")

    # Convert video if requested
    if not args.analyze_only:
        if is_compliant and args.bitrate == 30:
            print("\n✅ Video is already compliant. Skipping conversion.")
            print("Use --bitrate to force conversion with different settings.")
        else:
            print(f"\n🔧 Converting video...")
            success = convert_video(
                input_path, output_path,
                args.bitrate, args.fps, args.resolution, args.audio_bitrate
            )

            if success:
                # Analyze converted video
                print("\n🔍 Analyzing converted video...")
                new_info = get_video_info(output_path)
                if new_info:
                    is_new_compliant, new_issues, _ = analyze_video_compliance(new_info)
                    if is_new_compliant:
                        print("🎉 Converted video meets all requirements!")
                    else:
                        print("⚠️ Some issues remain:")
                        for issue in new_issues:
                            print(f"  {issue}")

                print(f"\n✅ Converted video saved: {output_path}")
                print("📁 Use this file for BytePlus Clone Avatar upload")
            else:
                sys.exit(1)


if __name__ == "__main__":
    main()
