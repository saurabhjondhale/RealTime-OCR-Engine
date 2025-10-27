<?php
/*
Plugin Name: Heritage Foundation Real-Time OCR Extraction Bubble
Description: Adds a floating bubble with multilingual OCR extraction using Tesseract.js.
Version: 1.0
Author: Saurabh Jondhale
Company: Heritage Foundation
*/

function heritage_ocr_floating_bubble() {
    ?>
    <style>
      /* Body styles removed to prevent conflicts with theme CSS */
      #ocr-bubble {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background:#ce651f;
        color:#111;
        padding:0 12px;
        height:60px;
        border-radius:30px;
        display:flex;
        align-items:center;
        justify-content:center;
        cursor:pointer;
        z-index:10000;
        font-weight:bold;
        text-align:center;
        user-select:none;
      }
      #ocr-modal {
        display:none;
        position:fixed;
        inset:0;
        background:rgba(0,0,0,0.9);
        backdrop-filter:blur(6px);
        z-index:10001;
        overflow-y:auto;
        flex-direction:column;
        align-items:center;
        justify-content:flex-start;
        padding:20px;
      }
      #ocr-modal.flex {
        display:flex;
      }
      #ocr-close-btn {
        position:absolute;
        top:12px;
        right:12px;
        width:36px; height:36px;
        background:#00ffaa;
        color:#111;
        border:none;
        border-radius:50%;
        font-size:20px;
        cursor:pointer;
        z-index:10;
      }
      #ocr-container {
        width: 100%;
        max-width: 600px;
        display:flex;
        flex-direction:column;
        align-items:center;
      }
      video {
        display:block;
        width:100%;
        max-width:600px;
        height:400px;
        object-fit:cover;
        border-radius:12px;
        border:3px solid #ce651f;
        background:#000;
      }
      .controls {
        width:100%;
        margin-top:10px;
        display:flex;
        flex-direction:column;
        align-items:center;
      }
      select {
        width:90%;
        padding:10px;
        font-size:16px;
        margin-bottom:15px;
        border-radius:8px;
        background:#222;
        color:#ce651f;
        border:none;
      }
      .button-row {
        display:flex;
        align-items:center;
        justify-content:center;
        gap:20px;
        margin-bottom:15px;
      }
      #capture-btn {
        width:70px;
        height:70px;
        border-radius:50%;
        background:#e49336;
        color:#111;
        font-size:30px;
        border:none;
        cursor:pointer;
        font-weight:bold;
        display:flex;
        align-items:center;
        justify-content:center;
        box-shadow:0 4px 10px rgba(0,255,170,0.7);
      }
      #reset-btn {
        padding:10px 14px;
        font-size:16px;
        border-radius:8px;
        border:none;
        background:#96745e;
        color:#fff;
        cursor:pointer;
      }
      #ocr-result {
        width:90%;
        min-height:120px;
        max-height:180px;
        background:#222;
        border:2px solid #ce651f;
        border-radius:8px;
        padding:10px;
        color:#fff;
        overflow-y:auto;
        white-space:pre-wrap;
        text-align:left;
      }
      #progress, #lang-detected {
        color:#aaa;
        font-size:14px;
        margin-top:4px;
        text-align:center;
      }
    </style>

    <div id="ocr-bubble" title="Open Heritage Foundation Real-Time OCR Extraction">🏛 Real-Time OCR Extraction</div>

    <div id="ocr-modal" aria-modal="true" role="dialog" aria-label="OCR Camera Modal">
      <button id="ocr-close-btn" aria-label="Close OCR Modal">×</button>
      <div id="ocr-container">
        <video id="video" autoplay playsinline muted></video>
        <canvas id="canvas" style="display:none;"></canvas>

        <div class="controls">
          <select id="langSelect" aria-label="Select OCR Language">
            <option value="eng">English</option>
            <option value="hin">[translate:Hindi (हिंदी)]</option>
            <option value="mar">[translate:Marathi (मराठी)]</option>
            <option value="san">[translate:Sanskrit (संस्कृत)]</option>
            <option value="mod">[translate:Modi Script (मोदी)]</option>
            <option value="tam">[translate:Tamil (தமிழ்)]</option>
            <option value="tel">[translate:Telugu (తెలుగు)]</option>
            <option value="kan">[translate:Kannada (ಕನ್ನಡ)]</option>
            <option value="mal">[translate:Malayalam (മലയാളം)]</option>
            <option value="ben">[translate:Bengali (বাংলা)]</option>
            <option value="guj">[translate:Gujarati (ગુજરાતી)]</option>
            <option value="ori">[translate:Odia (ଓଡ଼ିଆ)]</option>
            <option value="pan">[translate:Punjabi (ਪੰਜਾਬੀ)]</option>
            <option value="nep">[translate:Nepali (नेपाली)]</option>
            <option value="sin">[translate:Sinhala (සිංහල)]</option>
            <option value="tha">[translate:Thai (ไทย)]</option>
            <option value="ara">[translate:Arabic (العربية)]</option>
          </select>

          <div class="button-row">
            <button id="capture-btn" aria-label="Capture Image for OCR">📸</button>
            <button id="reset-btn" aria-label="Reset OCR Result">🔄</button>
          </div>

          <div id="progress" aria-live="polite" role="status"></div>
          <div id="lang-detected" aria-live="polite"></div>

          <div id="ocr-result" tabindex="0" aria-live="polite" aria-atomic="true">OCR text will appear here...</div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <script>
      const ocrBubble = document.getElementById('ocr-bubble');
      const ocrModal = document.getElementById('ocr-modal');
      const ocrCloseBtn = document.getElementById('ocr-close-btn');
      const video = document.getElementById('video');
      const canvas = document.getElementById('canvas');
      const captureBtn = document.getElementById('capture-btn');
      const resetBtn = document.getElementById('reset-btn');
      const langSelect = document.getElementById('langSelect');
      const ocrResult = document.getElementById('ocr-result');
      const progress = document.getElementById('progress');
      const langDetected = document.getElementById('lang-detected');

      let stream;

      async function startCamera() {
        try {
          stream = await navigator.mediaDevices.getUserMedia({
            video:{ facingMode:'environment' },
            audio:false
          });
          video.srcObject = stream;
          await video.play();
        } catch(err) {
          console.error(err);
          ocrResult.textContent = "❌ Camera access denied or not available.";
        }
      }

      function stopCamera() {
        if(stream){
          stream.getTracks().forEach(t=>t.stop());
          stream=null;
        }
      }

      function wrapForeignWords(text){
        return text.replace(/([\u0080-\uFFFF]+)/g, match=>`[translate:${match}]`);
      }

      captureBtn.addEventListener('click', async ()=>{
        const ctx = canvas.getContext('2d');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video,0,0,canvas.width,canvas.height);

        const lang = langSelect.value;
        progress.textContent = `Processing OCR (${lang})...`;
        ocrResult.textContent = "⏳ Reading text...";
        langDetected.textContent = "";

        try{
          const result = await Tesseract.recognize(canvas, lang, {
            logger: info => {
              if(info.status==='recognizing text'){
                progress.textContent = `Progress: ${(info.progress*100).toFixed(0)}% (${lang})`;
              }
            }
          });

          let rawText = result.data.text.trim() || "⚠️ No text detected.";
          ocrResult.textContent = wrapForeignWords(rawText);
          progress.textContent="✅ Done!";
          langDetected.textContent=`Detected script: ${lang.toUpperCase()}`;
        }catch(err){
          ocrResult.textContent="❌ OCR failed: "+err.message;
          progress.textContent="";
        }
      });

      resetBtn.addEventListener('click', ()=>{
        ocrResult.textContent="OCR text will appear here...";
        progress.textContent="";
        langDetected.textContent="";
      });

      ocrBubble.addEventListener('click', ()=>{
        ocrModal.classList.add('flex');
        setTimeout(startCamera,100);
      });

      ocrCloseBtn.addEventListener('click', ()=>{
        ocrModal.classList.remove('flex');
        resetBtn.click();
        stopCamera();
      });

      langSelect.addEventListener('change', ()=>{
        langDetected.textContent=`🌐 Language switched to: ${langSelect.options[langSelect.selectedIndex].text}`;
      });
    </script>
    <?php
}
add_action('wp_footer', 'heritage_ocr_floating_bubble');
