FROM python:3.9-slim

WORKDIR /app

# Install dependencies
RUN pip install --no-cache-dir fastapi uvicorn beautifulsoup4 requests

# Copy the application code
COPY ./reader_api.py /app/

# Expose the port the app runs on
EXPOSE 8000

# Run the application
CMD ["uvicorn", "reader_api:app", "--host", "0.0.0.0", "--port", "8000"]
