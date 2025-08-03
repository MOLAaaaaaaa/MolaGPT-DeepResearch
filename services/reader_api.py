from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import requests
from bs4 import BeautifulSoup

app = FastAPI()

class URLRequest(BaseModel):
    url: str

def get_content_from_url(url: str):
    """
    Fetches and extracts the main textual content from a given URL.
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        }
        response = requests.get(url, headers=headers, timeout=15)
        response.raise_for_status()

        # Use BeautifulSoup to parse the HTML
        soup = BeautifulSoup(response.content, 'html.parser')

        # Remove script and style elements
        for script_or_style in soup(["script", "style"]):
            script_or_style.decompose()

        # Get text and clean it up
        text = soup.get_text()
        lines = (line.strip() for line in text.splitlines())
        chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
        text = '\n'.join(chunk for chunk in chunks if chunk)

        return text

    except requests.RequestException as e:
        raise HTTPException(status_code=400, detail=f"Error fetching URL: {e}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error processing URL content: {e}")

@app.post("/read")
async def read_url(request: URLRequest):
    """
    API endpoint to read the content of a URL.
    """
    if not request.url:
        raise HTTPException(status_code=400, detail="URL parameter is required.")
    
    content = get_content_from_url(request.url)
    
    if not content:
        raise HTTPException(status_code=404, detail="Could not extract content from the URL.")
        
    return {"url": request.url, "content": content}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
