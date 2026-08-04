FROM python:3.11-slim

WORKDIR /opt/quizanalytics

# kaleido (Plotly->PNG rasterization for PDF export, see pdf_export.py) needs a
# headless Chrome/Chromium. Its own get_chrome_sync() downloads "Chrome for
# Testing", which has no official Linux arm64 build — on an arm64 host that
# download is an x86_64 binary that won't reliably run. Installing the
# distro-packaged, architecture-native `chromium` here means pdf_export.py's
# _system_browser_path() finds a working browser on both amd64 and arm64
# without ever needing that download.
RUN apt-get update && apt-get install -y --no-install-recommends \
    chromium \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# `analytics/` here should be a copy of the analytics/ package from your
# Interactive-quiz-analytics repo (unmodified) — see the README for how to
# sync it. app.py imports it as a top-level package, so it must sit next to
# app.py, not nested inside another folder.
COPY analytics/ ./analytics/
COPY app.py .

# 127.0.0.1 only would be meaningless inside a container's own network
# namespace (that's the container's loopback, not the host's) — instead,
# keep this off the host's public interface entirely by only publishing the
# port to localhost when you run it, e.g.:
#   docker run -p 127.0.0.1:8600:8600 quiz-quizanalytics-service
EXPOSE 8600
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8600"]
