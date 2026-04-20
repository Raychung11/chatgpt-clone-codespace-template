#!/bin/bash
# Setup script for Clone Avatar Training project
# This script creates a virtual environment and installs dependencies

set -e  # Exit on error

echo "🚀 Setting up Clone Avatar Training environment..."

# Create virtual environment if it doesn't exist
if [ ! -d "venv" ]; then
    echo "📦 Creating virtual environment..."
    python3 -m venv venv
else
    echo "✅ Virtual environment already exists"
fi

# Activate virtual environment
echo "🔌 Activating virtual environment..."
source venv/bin/activate

# Upgrade pip
echo "⬆️ Upgrading pip..."
pip install --upgrade pip

# Install Python dependencies
echo "📚 Installing Python dependencies..."
pip install -r requirements.txt

# Check if ffmpeg is installed
echo "🎬 Checking ffmpeg installation..."
if command -v ffmpeg >/dev/null 2>&1; then
    echo "✅ ffmpeg is already installed"
    ffmpeg -version | head -n 1
else
    echo "❌ ffmpeg not found"
    echo "Please install ffmpeg:"
    echo "  macOS: brew install ffmpeg"
    echo "  Ubuntu: sudo apt install ffmpeg"
    echo "  Windows: Download from https://ffmpeg.org/download.html"
fi

# Create output directory
echo "📁 Creating output directory..."
mkdir -p output

# Copy environment file if it doesn't exist
if [ ! -f "config/.env" ]; then
    echo "⚙️ Creating environment configuration..."
    cp config/.env.example config/.env
    echo "✏️ Please edit config/.env with your actual API credentials"
else
    echo "✅ Environment configuration already exists"
fi

echo ""
echo "🎉 Setup complete!"
echo "To activate the environment in the future, run:"
echo "    source venv/bin/activate"
echo ""
echo "Next steps:"
echo "1. Edit config/.env with your API credentials (if needed)"
echo "2. Convert your video bitrate: ./scripts/convert_video.py"
echo "3. Start the clone avatar workflow!"
