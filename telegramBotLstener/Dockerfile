# Use the official Python image from the Docker Hub
FROM python:3.11-slim

# Set the working directory in the container
WORKDIR /app

# Copy requirements first for better caching
COPY requirements.txt .

# Install the dependencies
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt

# Copy the rest of the application
COPY . /app

# Run the bot when the container starts
# Required environment variables (set via .env file or docker-compose):
# - TELEGRAM_BOT_TOKEN: Your Telegram bot token from @BotFather
# - PAYMENT_API_TOKEN: API access token (Authorization: Bearer ...)
# Optional:
# - PAYMENT_WEBHOOK_URL: Your webhook URL (default: https://admin-service.johnrak.online/api/payment/webhook)
# Use unbuffered Python output to see logs immediately
CMD ["python3", "-u", "listener.py"]
