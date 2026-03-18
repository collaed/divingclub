@extends('admin.guide.partials.guide-layout')
@section('content')
<p>All API keys are stored in the <code>.env</code> file. After changing keys, run <code>php artisan config:clear</code>.</p>

<h5>OAuth / Social Login</h5>
<p>The system supports 5 OAuth providers for "Login with..." buttons. Each requires a Client ID and Client Secret.</p>

<table class="table table-sm">
    <thead><tr><th>Provider</th><th>Env Variables</th><th>Setup URL</th><th>Callback URL</th></tr></thead>
    <tbody>
        <tr>
            <td>Google</td>
            <td><code>GOOGLE_CLIENT_ID</code><br><code>GOOGLE_CLIENT_SECRET</code></td>
            <td><a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a></td>
            <td><code>{APP_URL}/auth/google/callback</code></td>
        </tr>
        <tr>
            <td>Facebook</td>
            <td><code>FACEBOOK_CLIENT_ID</code><br><code>FACEBOOK_CLIENT_SECRET</code></td>
            <td><a href="https://developers.facebook.com/apps" target="_blank">Meta for Developers</a></td>
            <td><code>{APP_URL}/auth/facebook/callback</code></td>
        </tr>
        <tr>
            <td>Microsoft</td>
            <td><code>MICROSOFT_CLIENT_ID</code><br><code>MICROSOFT_CLIENT_SECRET</code></td>
            <td><a href="https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps" target="_blank">Azure Portal</a></td>
            <td><code>{APP_URL}/auth/microsoft/callback</code></td>
        </tr>
        <tr>
            <td>X (Twitter)</td>
            <td><code>X_CLIENT_ID</code><br><code>X_CLIENT_SECRET</code></td>
            <td><a href="https://developer.x.com/en/portal/dashboard" target="_blank">X Developer Portal</a></td>
            <td><code>{APP_URL}/auth/x/callback</code></td>
        </tr>
        <tr>
            <td>Amazon</td>
            <td><code>AMAZON_CLIENT_ID</code><br><code>AMAZON_CLIENT_SECRET</code></td>
            <td><a href="https://developer.amazon.com/loginwithamazon/console/site/lwa/overview.html" target="_blank">Amazon Developer</a></td>
            <td><code>{APP_URL}/auth/amazon/callback</code></td>
        </tr>
    </tbody>
</table>

<div class="alert alert-info">
    <strong>Tip:</strong> Google OAuth is the most commonly used. Start with that one. All providers are free to set up.
</div>

<h5>Google Maps</h5>
<table class="table table-sm">
    <tr><th>Env Variable</th><td><code>GOOGLE_MAPS_KEY</code></td></tr>
    <tr><th>Setup</th><td><a href="https://console.cloud.google.com/apis/library/maps-embed-backend.googleapis.com" target="_blank">Google Cloud Console → Maps Embed API</a></td></tr>
    <tr><th>Cost</th><td>Free tier: unlimited embeds. The Maps Embed API has no usage limits.</td></tr>
    <tr><th>Used for</th><td>Embedded map on event detail pages showing the event location</td></tr>
</table>
<p>Without a key, event locations link to Google Maps search instead of embedding.</p>

<h5>Translation API (Optional)</h5>
<p>For auto-translating content to the 11 supported languages. Set <code>TRANSLATION_DRIVER</code> and the corresponding key.</p>

<table class="table table-sm">
    <thead><tr><th>Service</th><th>Cost</th><th>Env Variables</th><th>Notes</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>LibreTranslate</strong> (recommended)</td>
            <td>🆓 Free — self-hosted</td>
            <td><code>TRANSLATION_DRIVER=libretranslate</code><br><code>LIBRETRANSLATE_URL=http://localhost:5000</code></td>
            <td>Run via Docker: <code>docker run -p 5000:5000 libretranslate/libretranslate</code>. No API key needed for self-hosted.</td>
        </tr>
        <tr>
            <td><strong>Argos Translate</strong></td>
            <td>🆓 Free — self-hosted</td>
            <td><code>TRANSLATION_DRIVER=argos</code><br><code>ARGOS_URL=http://localhost:5100</code></td>
            <td>Python-based, offline. Good for privacy-sensitive deployments.</td>
        </tr>
        <tr>
            <td>DeepL</td>
            <td>Free tier: 500K chars/month</td>
            <td><code>TRANSLATION_DRIVER=deepl</code><br><code>DEEPL_API_KEY=your_key</code></td>
            <td><a href="https://www.deepl.com/pro-api" target="_blank">deepl.com/pro-api</a> — best quality for European languages</td>
        </tr>
        <tr>
            <td>Google Translate</td>
            <td>$20/million chars</td>
            <td><code>TRANSLATION_DRIVER=google</code><br><code>GOOGLE_TRANSLATE_KEY=your_key</code></td>
            <td><a href="https://cloud.google.com/translate" target="_blank">Cloud Translation API</a></td>
        </tr>
    </tbody>
</table>

<h5>OCR API (Optional)</h5>
<p>For auto-reading medical certificates and diving brevets from uploaded scans.</p>

<table class="table table-sm">
    <thead><tr><th>Service</th><th>Cost</th><th>Env Variables</th><th>Notes</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Tesseract OCR</strong> (recommended)</td>
            <td>🆓 Free — local install</td>
            <td><code>OCR_DRIVER=tesseract</code></td>
            <td>Install: <code>sudo apt install tesseract-ocr tesseract-ocr-fra tesseract-ocr-deu</code>. Runs locally, no API key. Good for printed text.</td>
        </tr>
        <tr>
            <td><strong>PaddleOCR</strong></td>
            <td>🆓 Free — self-hosted</td>
            <td><code>OCR_DRIVER=paddle</code><br><code>PADDLE_URL=http://localhost:8866</code></td>
            <td>Python-based, excellent multilingual support. Docker available.</td>
        </tr>
        <tr>
            <td>Google Vision</td>
            <td>Free: 1K/month, then $1.50/1K</td>
            <td><code>OCR_DRIVER=google_vision</code><br><code>GOOGLE_VISION_KEY=your_key</code></td>
            <td><a href="https://cloud.google.com/vision" target="_blank">Cloud Vision API</a> — best accuracy for handwritten text</td>
        </tr>
        <tr>
            <td>Azure Computer Vision</td>
            <td>Free: 5K/month</td>
            <td><code>OCR_DRIVER=azure</code><br><code>AZURE_VISION_KEY=your_key</code><br><code>AZURE_VISION_ENDPOINT=your_endpoint</code></td>
            <td><a href="https://azure.microsoft.com/en-us/products/ai-services/ai-vision" target="_blank">Azure AI Vision</a></td>
        </tr>
    </tbody>
</table>

<h5>LLM / AI Assistant (Optional)</h5>
<p>For intelligent document parsing, auto-categorization, and natural language queries.</p>

<table class="table table-sm">
    <thead><tr><th>Service</th><th>Cost</th><th>Env Variables</th><th>Notes</th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Ollama</strong> (recommended)</td>
            <td>🆓 Free — local</td>
            <td><code>LLM_DRIVER=ollama</code><br><code>OLLAMA_URL=http://localhost:11434</code><br><code>OLLAMA_MODEL=llama3.2</code></td>
            <td>Install: <code>curl -fsSL https://ollama.com/install.sh | sh && ollama pull llama3.2</code>. Runs entirely on your server. No data leaves your network.</td>
        </tr>
        <tr>
            <td><strong>LM Studio</strong></td>
            <td>🆓 Free — local</td>
            <td><code>LLM_DRIVER=openai</code><br><code>LLM_URL=http://localhost:1234/v1</code><br><code>LLM_API_KEY=lm-studio</code></td>
            <td>OpenAI-compatible API. Download models from Hugging Face.</td>
        </tr>
        <tr>
            <td>OpenAI</td>
            <td>Pay-per-token</td>
            <td><code>LLM_DRIVER=openai</code><br><code>OPENAI_API_KEY=your_key</code></td>
            <td><a href="https://platform.openai.com" target="_blank">platform.openai.com</a></td>
        </tr>
        <tr>
            <td>Anthropic Claude</td>
            <td>Pay-per-token</td>
            <td><code>LLM_DRIVER=anthropic</code><br><code>ANTHROPIC_API_KEY=your_key</code></td>
            <td><a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></td>
        </tr>
    </tbody>
</table>

<h5>SMTP / Email</h5>
<table class="table table-sm">
    <thead><tr><th>Service</th><th>Cost</th><th>Notes</th></tr></thead>
    <tbody>
        <tr><td><strong>Brevo (ex-Sendinblue)</strong></td><td>🆓 Free: 300 emails/day</td><td>Good for small clubs. <a href="https://www.brevo.com" target="_blank">brevo.com</a></td></tr>
        <tr><td><strong>Mailgun</strong></td><td>Free: 100 emails/day (Flex plan)</td><td><a href="https://www.mailgun.com" target="_blank">mailgun.com</a></td></tr>
        <tr><td>Amazon SES</td><td>$0.10/1K emails</td><td>Cheapest at scale</td></tr>
        <tr><td>Postmark</td><td>$15/month for 10K</td><td>Best deliverability</td></tr>
    </tbody>
</table>

<h5>Current Status</h5>
<img src="/images/guide/admin-guide-api.png" class="img-fluid rounded border mb-3" alt="API Keys Guide" style="max-height:300px">
<p>Check the current configuration status at <a href="{{ route('admin.settings.index') }}">Settings → API Keys & Configuration</a>.</p>

<div class="alert alert-success">
    <strong>Recommendation for a small club:</strong> Start with Google OAuth (free), Tesseract OCR (free, local), LibreTranslate (free, Docker), Ollama (free, local), and Brevo SMTP (free, 300/day). Total cost: €0.
</div>
@endsection
