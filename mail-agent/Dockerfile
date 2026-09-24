FROM python:3.12-slim

# ffmpeg : conversion audio pour TTS_PROVIDER=edge
RUN apt-get update && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY mail_agent ./mail_agent

RUN useradd --create-home agent && mkdir -p /app/data && chown agent /app/data
USER agent
ENV DATA_DIR=/app/data PYTHONUNBUFFERED=1
CMD ["python", "-m", "mail_agent"]
