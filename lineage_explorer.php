<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config.php';
$conn = getDB();

$page_title = 'ناسینەوە و جیاکردنەوەی ڕەچەڵەک';
$active_nav = 'lineage_explorer';
$sys_module = 'lineage';

require_once 'includes/layout.php';

$families = [];
$fres = $conn->query("SELECT id, family_name FROM families ORDER BY family_name ASC");
while($f = $fres->fetch_assoc()) $families[] = $f;
?>

<style>
    .lineage-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .search-section {
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: var(--shadow-md);
        margin-bottom: 30px;
        border: 2px solid var(--maroon);
    }
    .search-input-wrapper {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .search-input-wrapper textarea {
        width: 100%;
        min-height: 120px;
        padding: 20px;
        font-size: 1.1rem;
        border: 2px solid #eee;
        border-radius: 12px;
        font-family: 'Rabar022', sans-serif;
        resize: vertical;
        outline: none;
        transition: 0.3s;
    }
    .search-input-wrapper textarea:focus {
        border-color: var(--maroon);
        box-shadow: 0 0 0 4px rgba(90, 2, 2, 0.1);
    }
    .btn-recognize {
        align-self: flex-end;
        padding: 12px 30px;
        font-size: 1rem;
        background: var(--maroon);
        color: #fff;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-weight: bold;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .btn-recognize:hover {
        background: var(--maroon-hover);
        transform: translateY(-2px);
    }

    /* Results Styling */
    .results-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    .result-card {
        background: #fff;
        border-radius: 15px;
        padding: 25px;
        box-shadow: var(--shadow-sm);
        border: 1px solid #eee;
    }
    .result-card h4 {
        color: var(--maroon);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f5f5f5;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .chain-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .chain-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: #f9f9f9;
        border-radius: 8px;
        font-size: 0.95rem;
        border-right: 4px solid var(--gold);
    }
    .chain-item .index {
        background: var(--maroon);
        color: #fff;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
    }
    .bridge-point {
        background: #e8f5e9;
        border: 1px solid #c8e6c9;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
    }
    .bridge-point h5 {
        color: #2e7d32;
        margin-bottom: 10px;
    }
    .bridge-member {
        display: flex;
        align-items: center;
        gap: 15px;
        background: #fff;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #c8e6c9;
    }
    .bridge-member img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
    }

    .loader {
        display: none;
        width: 20px;
        height: 20px;
        border: 3px solid #fff;
        border-bottom-color: transparent;
        border-radius: 50%;
        animation: rotation 1s linear infinite;
    }
    @keyframes rotation {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .info-box {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .info-box.blue { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
    .info-box.yellow { background: #fffde7; color: #f57f17; border: 1px solid #fff9c4; }
    .info-box.red { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

    .generations-count-badge {
        background: linear-gradient(135deg, var(--maroon), #8b0000);
        color: #fff;
        padding: 4px 18px;
        border-radius: 30px;
        font-size: 1.45rem;
        font-weight: 800;
        display: inline-block;
        margin: 0 8px;
        box-shadow: 0 4px 12px rgba(90, 2, 2, 0.25);
        border: 2px solid var(--gold);
        vertical-align: middle;
        font-family: 'Outfit', 'Inter', sans-serif;
        transform: scale(1.05);
        transition: 0.3s;
    }

    .chain-item.highlight-shared {
        background: linear-gradient(135deg, #fffbeb, #fef3c7) !important;
        border: 2px solid #fbbf24 !important;
        box-shadow: 0 0 15px rgba(251, 191, 36, 0.45) !important;
        transform: scale(1.03);
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border-right: 5px solid #fbbf24 !important;
    }

    /* Kinship & AI Styles */
    .kinship-card {
        background: #fff;
        border-radius: 15px;
        padding: 25px;
        margin-top: 25px;
        border: 1px solid #e2e8f0;
        box-shadow: var(--shadow-sm);
    }
    .uncles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }
    .uncle-badge {
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: 0.2s;
    }
    .uncle-badge:hover {
        background: #eff6ff;
        border-color: #3b82f6;
        transform: translateY(-2px);
    }
    .ancestors-timeline {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        margin-top: 15px;
        padding: 15px;
        background: #fdfaf6;
        border-radius: 10px;
        border: 1px dashed #d4af37;
    }
    .ancestor-pill {
        background: #fff;
        border: 1px solid #e2e8f0;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .ancestor-pill.shaho {
        background: linear-gradient(135deg, var(--maroon), #8b0000);
        color: #fff;
        border-color: var(--gold);
        font-weight: bold;
    }
    .ai-card {
        background: linear-gradient(135deg, #ffffff, #fbfbfe);
        border: 2px solid #818cf8;
        border-radius: 15px;
        padding: 25px;
        margin-top: 25px;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.08);
    }
    .btn-ai-analyze {
        background: linear-gradient(135deg, #4f46e5, #7c3aed);
        color: white;
        border: none;
        padding: 12px 28px;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: bold;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        transition: 0.3s;
    }
    .btn-ai-analyze:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(79, 70, 229, 0.45);
    }
    .ai-output-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 25px;
        margin-top: 20px;
        border: 1px solid #e2e8f0;
        line-height: 2;
        font-size: 1.05rem;
        color: #1e293b;
    }
</style>

<div class="lineage-container">
    <div class="search-section">
        <div class="search-input-wrapper">
            <h3 style="margin-bottom: 5px;">🧬 بزوێنەری ناسینەوە و جیاکردنەوەی ڕەچەڵەک</h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 15px;">ناوی ئەندامەکان و زنجیرەی ڕەچەڵەکەکە لێرە بنووسە (بۆ نموونە: ئەحمەد و محەمەد ابناء محمود بن علی ...)</p>
            <textarea id="lineageInput" placeholder="لێرە دەست بە نووسین بکە..."></textarea>
            <button class="btn-recognize" onclick="recognizeLineage()">
                <span class="loader" id="recognizeLoader"></span>
                <span>پڕۆسێس بکە</span>
            </button>
        </div>
    </div>

    <div id="lineageResults" style="display: none;">
        <div class="results-grid">
            <!-- Recognized Chain -->
            <div class="result-card">
                <div id="siblingsSection" style="margin-bottom: 25px; display: none;">
                    <h4 style="border-bottom-color: #bbdefb; color: #1565c0;">👤 ئەندام / کەسەکە و براکان</h4>
                    <div id="siblingsList" class="chain-list"></div>
                </div>

                <h4>📜 زنجیرەی باوباپیران</h4>
                <div id="recognizedChain" class="chain-list"></div>
            </div>

            <!-- Database Comparison -->
            <div class="result-card">
                <h4>🔍 بەراوردکاری داتابەیس</h4>
                <div id="dbComparison">
                    <div class="info-box blue">
                        <span>ℹ️</span>
                        <span>تکایە سەرەتا پڕۆسێس بکە بۆ دۆزینەوەی خاڵی بەیەکبەستن.</span>
                    </div>
                </div>

                <div id="bridgeSection" style="display: none;">
                    <div class="bridge-point">
                        <h5>🔗 خاڵی بەیەکبەستن (Bridge Point)</h5>
                        <p style="font-size: 0.85rem; color: #555; margin-bottom: 10px;">باشترین ئەندام کە لە داتابەیسدا دۆزرایەوە و دەکرێت ئەم زنجیرەیەی پێوە ببەسترێتەوە:</p>
                        <div id="bridgeMember" class="bridge-member"></div>
                        
                        <!-- Candidates Selection -->
                        <div id="candidatesWrapper" style="margin-top: 15px;">
                            <button type="button" class="lib-btn" style="width: 100%; font-size: 0.8rem; background: #f5f5f5; color: #333; border: 1px solid #ddd;" onclick="toggleCandidates()">
                                🔍 گۆڕینی خاڵی بەستنەوە / بینینی کاندیدەکانی تر
                            </button>
                            <div id="candidatesList" style="display: none; margin-top: 10px; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #eee; border-radius: 8px; padding: 10px;">
                                <!-- Candidates populated by JS -->
                            </div>
                        </div>
                    </div>
                    
                        <!-- Manual Search Trigger -->
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed #ddd;">
                            <p style="font-size: 0.8rem; color: #666; margin-bottom: 8px;">ئەگەر کەسەکەت نەدۆزیەوە، لێرە بگەڕێ:</p>
                            <div style="display: flex; gap: 5px;">
                                <input type="text" id="manualSearchInput" class="lib-input" style="flex: 1; padding: 8px;" placeholder="ناوی ئەندام یان کۆد...">
                                <button type="button" class="lib-btn" style="padding: 8px 15px; background: #607d8b;" onclick="searchBridgeManual()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div id="manualSearchResults" style="display: none; margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 4px; background: #fff;"></div>
                        </div>

                        <div style="margin-top: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: bold; font-size: 0.9rem;">🏢 دیاریکردنی بنەماڵە:</label>
                            <div style="display: flex; gap: 8px; margin-bottom: 20px;">
                                <select id="familySelect" class="lib-input" style="flex: 1; margin-bottom: 0;">
                                    <option value="0">--- بنەماڵە هەڵبژێرە ---</option>
                                    <?php foreach($families as $f): ?>
                                        <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['family_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="lib-btn" style="background: var(--maroon); color: #fff; padding: 0 15px; border-radius: 8px; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; border: none; cursor: pointer;" onclick="addNewFamilyQuick()" title="زیادکردنی بنەماڵەی نوێ">
                                    ➕
                                </button>
                            </div>

                            <!-- Shared Families Section -->
                            <div id="sharedFamiliesSection" style="display: none; margin-top: 10px; margin-bottom: 20px; padding: 12px; background: #fafafa; border: 1px dashed #ccc; border-radius: 8px;">
                                <div style="font-size: 0.85rem; font-weight: bold; color: #555; margin-bottom: 8px;">
                                    🤝 بنەماڵە هاوبەشەکان لە پیرخدری شاهۆ بۆ سەرەوە:
                                </div>
                                <div id="sharedFamiliesList" style="font-size: 0.85rem; color: #333; display: flex; flex-direction: column; gap: 6px;">
                                </div>
                            </div>

                            <button id="saveChainBtn" class="lib-btn lib-btn-primary" style="width: 100%; justify-content: center; padding: 15px;" onclick="saveAndLink()">
                                💾 هەڵگرتن و بەستنەوەی ڕەچەڵەک
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Kinship & Relatives Network Section -->
        <div id="kinshipSection" class="kinship-card" style="display: none;">
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px;">
                <h4 style="color: var(--maroon); margin: 0; font-size: 1.15rem; display: flex; align-items: center; gap: 10px;">
                    <span>🌳 بازنەی خزمایەتی و تۆڕی کەس و کار</span>
                </h4>
                <div id="kinshipFamilyBadge" style="background: #f1f5f9; color: #475569; padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: bold;"></div>
            </div>

            <!-- Uncles Section -->
            <div id="unclesWrapper" style="margin-bottom: 25px;">
                <div style="font-weight: bold; color: #1e293b; font-size: 0.95rem; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
                    <span>👨‍💼 مامەکانی کەسەکە (براکانی باوک لە داتابەیسدا):</span>
                </div>
                <div id="unclesGrid" class="uncles-grid"></div>
            </div>

            <!-- Ancestors Path Section -->
            <div id="ancestorsPathWrapper" style="margin-top: 20px;">
                <div style="font-weight: bold; color: #1e293b; font-size: 0.95rem; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                    <span>🧬 ڕەگی ڕەچەڵەک بەرەو سەرەوە تا پیرخدری شاهۆ:</span>
                    <span id="shahoGenerationsBadge" style="font-size: 0.85rem; color: #b45309; background: #fef3c7; padding: 4px 10px; border-radius: 12px; font-weight: bold;"></span>
                </div>
                <div id="ancestorsTimeline" class="ancestors-timeline"></div>
            </div>
        </div>

        <!-- AI Genealogical & Historical Advisor Section -->
        <div id="aiAdvisorSection" class="ai-card" style="display: none;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h4 style="color: #4338ca; margin: 0 0 5px 0; font-size: 1.2rem; display: flex; align-items: center; gap: 10px;">
                        <span>✨ ڕاوێژکاری ژیری دەستکرد بۆ نەسەب و مێژوو (Gemini AI)</span>
                    </h4>
                    <p style="color: #64748b; font-size: 0.88rem; margin: 0;">
                        شیکاری ئەدەبی، بەڵگەنامەی مێژوویی بنەماڵە، و ڕێنوێنی نەسەبزانی لەسەر ئەم ناوە بە ژیری دەستکرد.
                    </p>
                </div>
                <button type="button" class="btn-ai-analyze" onclick="triggerAIAnalysis()">
                    <span class="loader" id="aiLoader" style="display: none; border-color: #fff; border-bottom-color: transparent;"></span>
                    <span>✨ ئەنجامدانی شیکاری AI</span>
                </button>
            </div>

            <div id="aiOutputWrapper" style="display: none; margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span id="aiSourceTag" style="font-size: 0.8rem; background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 12px; font-weight: bold;"></span>
                    <button type="button" class="lib-btn" style="padding: 4px 12px; font-size: 0.8rem; background: #f8fafc;" onclick="copyAIText()">📋 لەبەرگرتنەوەی ڕاپۆرت</button>
                </div>
                <div id="aiOutputText" class="ai-output-box"></div>
            </div>
        </div>

        <!-- Detailed Validation Gaps -->
        <div id="validationSection" class="lib-card" style="margin-top: 30px; display: none;">
            <div class="card-header" style="background: #fffde7;">
                <h3 style="color: #f57f17;">⚠️ تێبینییەکان و دۆزینەوەی بۆشایی (Gap Detection)</h3>
            </div>
            <div id="validationList" style="padding: 20px;"></div>
        </div>
    </div>
</div>

<script>
let currentRecognition = null;

function updateSaveButton() {
    if (!currentRecognition) return;
    const btn = document.getElementById('saveChainBtn');
    if (!btn) return;

    const allSiblingsRegistered = currentRecognition.siblings.length > 0 && currentRecognition.foundSiblings.every(s => s !== null);
    const allChainRegistered = currentRecognition.missingChain.every((_, i) => currentRecognition.foundNodes[i] !== null);
    
    // Also check if there are any siblings at all. If 0 siblings and all chain found, it's also complete.
    const isFullyRegistered = allChainRegistered && (currentRecognition.siblings.length === 0 || allSiblingsRegistered);

    if (isFullyRegistered) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '✅ ئەم ڕەچەڵەکەیە پێشتر بە تەواوی تۆمار کراوە';
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.innerHTML = '💾 هەڵگرتن و بەستنەوەی ڕەچەڵەک';
    }
}

async function recognizeLineage() {
    const input = document.getElementById('lineageInput').value.trim();
    if (!input) return;

    const loader = document.getElementById('recognizeLoader');
    const resultsDiv = document.getElementById('lineageResults');
    const chainList = document.getElementById('recognizedChain');
    const sibsSection = document.getElementById('siblingsSection');
    const sibsList = document.getElementById('siblingsList');
    const dbComp = document.getElementById('dbComparison');
    const bridgeSection = document.getElementById('bridgeSection');
    const bridgeMemberDiv = document.getElementById('bridgeMember');
    const validSection = document.getElementById('validationSection');
    const validList = document.getElementById('validationList');

    loader.style.display = 'block';
    
    try {
        const response = await fetch('lineage_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=recognize&input=${encodeURIComponent(input)}`
        });
        
        const data = await response.json();
        currentRecognition = data;
        
        if (data.success) {
            resultsDiv.style.display = 'block';
            
            // 1. Show Chain
            chainList.innerHTML = '';
            const genTitles = ['باوک', 'باپیر', 'باپیری گەورە', 'باپیری چوارەم', 'باپیری پێنجەم', 'باپیری شەشەم'];
            data.missingChain.forEach((name, index) => {
                const found = data.foundNodes && data.foundNodes[index];
                const itemClass = found ? 'chain-item found' : 'chain-item';
                const foundTag = found ? `
                    <div style="margin-right: auto; display: flex; align-items: center; gap: 5px; color: #2e7d32; font-size: 0.8rem; font-weight: bold;">
                        <span title="ئەم ئەندامە لە داتابەیسدا هەیە">✅ مەوجود (${found.full_name})</span>
                    </div>
                ` : `
                    <div style="margin-right: auto; display: flex; align-items: center; gap: 5px; color: #757575; font-size: 0.8rem;">
                        <span>تۆمار نەکراوە</span>
                    </div>
                `;

                const roleBadge = genTitles[index] ? `<span style="font-size: 0.75rem; color: #777; margin-right: 6px;">(${genTitles[index]})</span>` : '';

                chainList.innerHTML += `
                    <div class="${itemClass}" data-member-id="${found ? found.id : 0}" style="${found ? 'background: #e8f5e9; border-right-color: #2e7d32;' : ''}">
                        <span class="index">${index + 1}</span>
                        <div style="flex: 1; display: flex; align-items: center;">
                            <div style="font-weight: ${found ? 'bold' : 'normal'};">${name}</div>
                            ${roleBadge}
                        </div>
                        ${foundTag}
                    </div>
                `;
            });

            // 2. Show Siblings / Person
            if (data.siblings && data.siblings.length > 0) {
                sibsSection.style.display = 'block';
                sibsList.innerHTML = '';
                data.siblings.forEach((name, index) => {
                    const found = data.foundSiblings && data.foundSiblings[index];
                    const foundInfo = found ? `
                        <div style="font-size: 0.8rem; color: #2e7d32; font-weight: bold; margin-top: 3px;">
                            ✅ لە داتابەیسدا تۆمارکراوە: ${found.full_name} (کۆد: ${found.member_code || found.id})
                        </div>
                    ` : `
                        <div style="font-size: 0.8rem; color: #1976d2; margin-top: 3px;">
                            🆕 ئەندامی نوێیە بۆ تۆمارکردن لە داتابەیس
                        </div>
                    `;
                    sibsList.innerHTML += `
                        <div class="chain-item" style="border-right-color: ${found ? '#2e7d32' : '#2196f3'}; background: ${found ? '#e8f5e9' : '#f9f9f9'};">
                            <span style="font-size: 1.1rem;">👤</span>
                            <div style="flex: 1;">
                                <div style="font-weight: ${found ? 'bold' : 'normal'}; font-size: 0.95rem;">${name}</div>
                                ${foundInfo}
                            </div>
                        </div>
                    `;
                });
            } else {
                sibsSection.style.display = 'none';
            }

            // 3. Show Bridge
            dbComp.innerHTML = ''; // Clear previous comparison info
            
            if (data.bridgeMember) {
                dbComp.innerHTML = `
                    <div class="info-box blue">
                        <span>✅</span>
                        <span>ئەندامێکی گونجاو دۆزرایەوە بۆ بەستنەوە.</span>
                    </div>
                `;
                bridgeSection.style.display = 'block';
                renderBridgeCard(data.bridgeMember);
                renderCandidatesList(data.foundNodes);
                
                // Auto-select family if found
                if (data.family_id && data.family_id != 0) {
                    document.getElementById('familySelect').value = data.family_id;
                } else if (data.bridgeMember && data.bridgeMember.family_id) {
                    document.getElementById('familySelect').value = data.bridgeMember.family_id;
                }
            }

            if (data.generations_to_shaho && data.generations_to_shaho > 0) {
                const genBadgeHtml = `
                    <div class="info-box" style="background: linear-gradient(135deg, #fdf2f2, #fff9f9); color: #5a0202; border-color: #ffcdd2; margin-bottom: 20px; display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 20px; border-radius: 12px; border: 2px solid; text-align: center; box-shadow: var(--shadow-sm); width: 100%;">
                        <div style="font-size: 2rem;">🧬</div>
                        <div style="font-size: 1.05rem; font-weight: bold; line-height: 1.6;">
                            ئەم زنجیرەیە دەگەڕێتەوە سەر <strong>ساداتی پیرخدری شاهۆ</strong>
                        </div>
                        <div style="margin-top: 10px; font-size: 1.15rem; font-weight: bold; color: #333;">
                            بە <span class="generations-count-badge">${data.generations_to_shaho}</span> پشت دەگاتەوە بە پیرخدری شاهۆ
                        </div>
                    </div>
                `;
                dbComp.insertAdjacentHTML('afterbegin', genBadgeHtml);
                
                // If no DB bridge was found, show the base bridge as the primary bridge
                if (!data.bridgeMember) {
                    bridgeSection.style.display = 'block';
                    bridgeMemberDiv.innerHTML = `
                        <div style="font-size: 2rem;">📜</div>
                        <div>
                            <div style="font-weight: bold; color: var(--maroon);">بەستنەوە بە نەسەبی بنچینەیی</div>
                            <div style="font-size: 0.8rem; color: #666;">زنجیرەکە لە السيد محمد الملقب ب (بيرخضر الشاهو)ـەوە دەست پێ دەکاتەوە بەرەو سەرەوە.</div>
                        </div>
                    `;
                }
            } else if (data.baseBridge) {
                const baseInfo = `
                    <div class="info-box" style="background: #e3f2fd; color: #0d47a1; border-color: #bbdefb; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 8px; border: 1px solid;">
                        <span style="font-size: 1.2rem;">🧬</span>
                        <span>ئەم زنجیرەیە دەگەڕێتەوە سەر <strong>ڕەچەڵەکی بنچینەیی</strong> (لە کەسی: ${data.baseBridge.name})</span>
                    </div>
                `;
                dbComp.insertAdjacentHTML('afterbegin', baseInfo);
                
                // If no DB bridge was found, show the base bridge as the primary bridge
                if (!data.bridgeMember) {
                    bridgeSection.style.display = 'block';
                    bridgeMemberDiv.innerHTML = `
                        <div style="font-size: 2rem;">📜</div>
                        <div>
                            <div style="font-weight: bold; color: var(--maroon);">بەستنەوە بە نەسەبی بنچینەیی</div>
                            <div style="font-size: 0.8rem; color: #666;">زنجیرەکە لە ${data.baseBridge.name}ـەوە دەست پێ دەکاتەوە بەرەو سەرەوە.</div>
                        </div>
                    `;
                }
            }
            
            if (!data.bridgeMember && !data.baseBridge) {
                dbComp.innerHTML = `
                    <div class="info-box yellow">
                        <span>⚠️</span>
                        <span>هیچ ئەندامێک نەدۆزرایەوە لە داتابەیسدا کە لەگەڵ ئەم زنجیرەیەی پێوە ببەسترێتەوە.</span>
                    </div>
                `;
                bridgeSection.style.display = 'none';
            }

            // 4. Validation Gaps
            if (data.validationErrors && data.validationErrors.length > 0) {
                validSection.style.display = 'block';
                validList.innerHTML = '';
                data.validationErrors.forEach(err => {
                    validList.innerHTML += `
                        <div class="info-box red" style="margin-bottom: 10px;">
                            <span>⚠️</span>
                            <span>${err}</span>
                        </div>
                    `;
                });
            } else {
                validSection.style.display = 'none';
            }

            // 5. Shared Families
            const sharedSection = document.getElementById('sharedFamiliesSection');
            const sharedList = document.getElementById('sharedFamiliesList');
            if (sharedSection && sharedList) {
                if (data.shared_families && data.shared_families.length > 0) {
                    sharedSection.style.display = 'block';
                    sharedList.innerHTML = '';
                    data.shared_families.forEach(item => {
                        const idsStr = JSON.stringify(item.shared_ancestor_ids);
                        sharedList.innerHTML += `
                            <div class="shared-family-row" onclick='highlightSharedAncestors(${idsStr}, this)' style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border: 1px solid #eee; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#fbbf24'; this.style.background='#fffbeb';" onmouseout="if(!this.classList.contains('active-highlight')) { this.style.borderColor='#eee'; this.style.background='#fff'; }">
                                <span style="font-weight: 600; color: #333; display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 1.1rem;">🔗</span> ${item.family_name}
                                </span>
                                <span style="background: linear-gradient(135deg, var(--maroon), #8b0000); color: white; padding: 4px 12px; border-radius: 20px; font-weight: bold; font-size: 0.8rem; border: 1px solid var(--gold); box-shadow: 0 2px 4px rgba(90, 2, 2, 0.15);">${item.shared_count} پشت</span>
                            </div>
                        `;
                    });
                } else {
                    sharedSection.style.display = 'none';
                }
            }
            
            // 6. Render Kinship & AI Sections
            renderKinship(data);
            
            updateSaveButton();
        } else {
            showAlert('هەڵە', data.message || 'هەڵەیەک ڕوویدا', 'error');
        }
    } catch (e) {
        console.error(e);
        showAlert('هەڵە', 'پەیوەندی لەکار کەوت', 'error');
    } finally {
        loader.style.display = 'none';
    }
}

function renderKinship(data) {
    const kinshipSec = document.getElementById('kinshipSection');
    const aiSec = document.getElementById('aiAdvisorSection');
    if (!kinshipSec || !aiSec) return;

    const kinship = data.kinship;
    if (!kinship) {
        kinshipSec.style.display = 'none';
        aiSec.style.display = 'none';
        return;
    }

    kinshipSec.style.display = 'block';
    aiSec.style.display = 'block';

    // Family badge
    const famSelect = document.getElementById('familySelect');
    let famName = data.family_name || (famSelect && famSelect.selectedIndex > 0 ? famSelect.options[famSelect.selectedIndex].text : '');
    if (!famName) famName = 'ساداتی پیرخدری شاهۆ';
    const famBadge = document.getElementById('kinshipFamilyBadge');
    if (famBadge) famBadge.innerHTML = '🏛️ ' + famName;

    // Uncles
    const unclesWrapper = document.getElementById('unclesWrapper');
    const unclesGrid = document.getElementById('unclesGrid');
    if (unclesWrapper && unclesGrid) {
        if (kinship.uncles && kinship.uncles.length > 0) {
            unclesWrapper.style.display = 'block';
            unclesGrid.innerHTML = '';
            kinship.uncles.forEach(u => {
                unclesGrid.innerHTML += `
                    <div class="uncle-badge">
                        <span style="font-size: 1.3rem;">👤</span>
                        <div style="flex: 1;">
                            <div style="font-weight: bold; color: #1e293b; font-size: 0.95rem;">${u.full_name}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">مامی کەسەکە | کۆد: ${u.member_code || u.id}</div>
                        </div>
                        <span style="font-size: 0.75rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-weight: bold;">مام</span>
                    </div>
                `;
            });
        } else {
            unclesWrapper.style.display = 'none';
        }
    }

    // Ancestors Path
    const pathWrapper = document.getElementById('ancestorsPathWrapper');
    const timeline = document.getElementById('ancestorsTimeline');
    const shahoBadge = document.getElementById('shahoGenerationsBadge');
    if (pathWrapper && timeline) {
        if (kinship.ancestors_path && kinship.ancestors_path.length > 0) {
            pathWrapper.style.display = 'block';
            timeline.innerHTML = '';
            
            if (kinship.generations_to_shaho > 0 && shahoBadge) {
                shahoBadge.innerHTML = `بە ${kinship.generations_to_shaho} پشت دەگاتەوە بە پیرخدری شاهۆ`;
            } else if (shahoBadge) {
                shahoBadge.innerHTML = 'بەستراوە بە شەجەرەی سەرەکی';
            }

            kinship.ancestors_path.slice(0, 10).forEach((a, idx) => {
                const isShaho = a.id == 241 || a.full_name.includes('بيرخضر') || a.full_name.includes('پیرخدر');
                const arrow = idx < Math.min(kinship.ancestors_path.length - 1, 9) ? '<span style="color: #94a3b8; font-weight: bold;">⬅️</span>' : '';
                timeline.innerHTML += `
                    <div class="ancestor-pill ${isShaho ? 'shaho' : ''}">
                        <span>${isShaho ? '👑' : '📍'}</span>
                        <span>${a.full_name}</span>
                    </div>
                    ${arrow}
                `;
            });
        } else {
            pathWrapper.style.display = 'none';
        }
    }
}

async function triggerAIAnalysis() {
    const input = document.getElementById('lineageInput').value.trim();
    if (!input) return;

    const loader = document.getElementById('aiLoader');
    const outputWrapper = document.getElementById('aiOutputWrapper');
    const outputText = document.getElementById('aiOutputText');
    const sourceTag = document.getElementById('aiSourceTag');

    loader.style.display = 'inline-block';
    outputText.innerHTML = '<div style="text-align: center; color: #6366f1; padding: 25px;"><span class="loader" style="display:inline-block; border-color:#6366f1; border-bottom-color:transparent; width:30px; height:30px;"></span><br><br><strong>ژیری دەستکرد لە شیکردنەوەی مێژوو و ئەنساب دایە... تکایە چاوەڕوان بە...</strong></div>';
    outputWrapper.style.display = 'block';

    let famId = 0;
    if (currentRecognition && currentRecognition.family_id) {
        famId = currentRecognition.family_id;
    } else {
        const famEl = document.getElementById('familySelect');
        if (famEl && famEl.value) {
            famId = famEl.value;
        }
    }

    let mId = 0;
    if (currentRecognition && Array.isArray(currentRecognition.foundSiblings)) {
        for (let s of currentRecognition.foundSiblings) {
            if (s && s.id) {
                mId = s.id;
                break;
            }
        }
    }

    let bId = 0;
    if (currentRecognition && currentRecognition.bridgeMember && currentRecognition.bridgeMember.id) {
        bId = currentRecognition.bridgeMember.id;
    }

    try {
        const res = await fetch('lineage_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=ai_analyze&input=${encodeURIComponent(input)}&family_id=${famId}&member_id=${mId}&bridge_id=${bId}`
        });
        const data = await res.json();
        if (data.success) {
            sourceTag.innerText = '⚡ ' + data.ai_source;
            
            // Clean markdown parser for headings, lists, blockquotes, bold
            let html = data.analysis_html
                .replace(/^### (.*$)/gim, '<h3 style="color: var(--maroon); margin: 18px 0 10px 0; font-size: 1.15rem;">$1</h3>')
                .replace(/^#### (.*$)/gim, '<h4 style="color: #1e293b; margin: 16px 0 8px 0; font-size: 1.05rem;">$1</h4>')
                .replace(/^\* (.*$)/gim, '<div style="margin: 6px 0; padding-right: 16px; position: relative;"><span style="position: absolute; right: 0; color: #6366f1; font-weight: bold;">•</span> $1</div>')
                .replace(/\*\*(.*?)\*\*/gim, '<strong style="color: #0f172a;">$1</strong>')
                .replace(/\*(.*?)\*/gim, '<em>$1</em>')
                .replace(/^\> (.*$)/gim, '<blockquote style="background: #f8fafc; border-right: 4px solid #6366f1; padding: 10px 15px; margin: 12px 0; border-radius: 6px;">$1</blockquote>')
                .replace(/\n/g, '<br>');

            if (data.top_shared_families && data.top_shared_families.length > 0) {
                let famCardsHtml = '<div style="margin-top: 22px; padding: 16px 18px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid #cbd5e1; border-radius: 14px;">' +
                    '<div style="font-weight: bold; color: #1e293b; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">' +
                    '<span style="display: flex; align-items: center; gap: 8px; font-size: 0.96rem; color: #3730a3;">' +
                    '<span>🌐 ئەو لق و بنەماڵانەی زۆرترین پشتی هاوبەشیان هەیە لە دوای پیرخدری شاهۆوە تا ئێستا:</span>' +
                    '</span>' +
                    '</div>' +
                    '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">';
                
                data.top_shared_families.forEach((fam, idx) => {
                    const label = fam.branch_label || ('بنەماڵەی ' + fam.family_name);
                    famCardsHtml += `
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between; gap: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                <strong style="color: #0f172a; font-size: 0.88rem; line-height: 1.4;">${idx + 1}. ${label}</strong>
                                <span style="background: #e0e7ff; color: #3730a3; font-size: 0.76rem; font-weight: bold; padding: 3px 8px; border-radius: 10px; white-space: nowrap;">${fam.shared_count} پشتی هاوبەش لە دوای شاهۆ</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #64748b; background: #f8fafc; padding: 6px 10px; border-radius: 8px; border-right: 3px solid #6366f1;">
                                📍 باپیری هاوبەش / خاڵی بەستن: <strong style="color: #1e293b;">${fam.common_ancestor}</strong>
                            </div>
                        </div>
                    `;
                });
                famCardsHtml += '</div></div>';
                html += famCardsHtml;
            } else {
                let noFamHtml = '<div style="margin-top: 18px; padding: 12px 16px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; color: #1e40af; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">' +
                    '<span style="font-size: 1.2rem;">ℹ️</span>' +
                    '<span><strong>تێبینی نەسەبی:</strong> تا دەگاتەوە بە پیرخدری شاهۆ هیچ پشتی هاوبەشتان لەگەڵ بنەماڵەکانی تردا نییە و ئەم لقە لە دوای پیرخدری شاهۆوە سەربەخۆیە؛ تەنها لە خودی گەورەمان پیرخدری شاهۆدا لەگەڵ سەرجەم بنەماڵەکاندا یەکدەگرنەوە.</span>' +
                    '</div>';
                html += noFamHtml;
            }

            outputText.innerHTML = html;
        } else {
            outputText.innerHTML = '<div style="color: #dc2626;">هەڵەیەک ڕوویدا لە شیکاری AI: ' + (data.message || '') + '</div>';
        }
    } catch (e) {
        outputText.innerHTML = '<div style="color: #dc2626;">پەیوەندی لەگەڵ سێرڤەر سەرکەوتوو نەبوو.</div>';
    } finally {
        loader.style.display = 'none';
    }
}

function copyAIText() {
    const textEl = document.getElementById('aiOutputText');
    if (!textEl) return;
    navigator.clipboard.writeText(textEl.innerText).then(() => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'ڕاپۆرتەکە لەبەرگیرایەوە ✅',
            showConfirmButton: false,
            timer: 2000
        });
    });
}

async function addNewFamilyQuick() {
    const { value: familyName } = await Swal.fire({
        title: 'زیادکردنی بنەماڵەی نوێ',
        input: 'text',
        inputLabel: 'ناوی بنەماڵە بنووسە:',
        inputPlaceholder: 'بۆ نموونە: بنەماڵەی سەیدانی سەیفوور',
        showCancelButton: true,
        confirmButtonText: 'تۆمارکردن',
        cancelButtonText: 'پاشگەزبوونەوە',
        confirmButtonColor: '#5a0202',
        inputValidator: (value) => {
            if (!value || !value.trim()) {
                return 'تکایە ناوی بنەماڵە بە دروستی بنووسە!';
            }
        }
    });

    if (familyName) {
        Swal.showLoading();
        
        try {
            const body = new URLSearchParams();
            body.append('action', 'add_family');
            body.append('family_name', familyName.trim());

            const response = await fetch('lineage_engine.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
            const data = await response.json();
            if (data.success) {
                const select = document.getElementById('familySelect');
                const opt = document.createElement('option');
                opt.value = data.family_id;
                opt.innerHTML = familyName.trim();
                opt.selected = true;
                select.appendChild(opt);
                
                Swal.fire({
                    icon: 'success',
                    title: 'سەرکەوتوو بوو',
                    text: 'بنەماڵەی نوێ بە سەرکەوتوویی زیادکرا!',
                    confirmButtonText: 'باشە',
                    confirmButtonColor: '#5a0202'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'هەڵە ڕوویدا',
                    text: data.message || 'زیادکردنی بنەماڵە سەرکەوتوو نەبوو!',
                    confirmButtonText: 'باشە',
                    confirmButtonColor: '#5a0202'
                });
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'هەڵە ڕوویدا',
                text: 'پەیوەندی لەگەڵ سێرڤەردا پچڕا!',
                confirmButtonText: 'باشە',
                confirmButtonColor: '#5a0202'
            });
        }
    }
}

async function saveAndLink() {
    const familyId = document.getElementById('familySelect').value;
    if (familyId == "0") {
        showAlert('ئاگاداری', 'تکایە بنەماڵەیەک هەڵبژێرە.', 'warning');
        return;
    }

    if (!currentRecognition) {
        showAlert('ئاگاداری', 'سەرەتا پڕۆسێس بکە.', 'info');
        return;
    }

    const confirmRes = await showConfirm('دڵنیایی', 'ئایا دڵنیایت لە تۆمارکردنی ئەم زنجیرەیە؟', 'question');
    if (!confirmRes.isConfirmed) return;

    const body = new URLSearchParams();
    body.append('action', 'save_and_link');
    body.append('siblings', JSON.stringify(currentRecognition.siblings));
    body.append('missingChain', JSON.stringify(currentRecognition.missingChain));
    body.append('bridgeId', currentRecognition.bridgeMember ? currentRecognition.bridgeMember.id : 0);
    body.append('baseBridgeChain', currentRecognition.baseBridge ? JSON.stringify(currentRecognition.baseBridge.fullChain) : '[]');
    body.append('bestChainIndex', currentRecognition.bridgeMember ? currentRecognition.bestChainIndex : (currentRecognition.baseBridge ? currentRecognition.baseBridge.index : -1));
    body.append('familyId', familyId);

    try {
        const response = await fetch('lineage_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        });
        const data = await response.json();
        if (data.success) {
            await showAlert('سەرکەوتوو', data.message, 'success');
            location.reload();
        } else {
            showAlert('هەڵە', data.message, 'error');
        }
    } catch (e) {
        showAlert('هەڵە', 'هەڵەیەک لە کاتی هەڵگرتن ڕوویدا', 'error');
    }
}

async function searchBridgeManual() {
    const query = document.getElementById('manualSearchInput').value.trim();
    if (query.length < 2) return;
    
    const resultsDiv = document.getElementById('manualSearchResults');
    resultsDiv.innerHTML = '<div style="padding: 10px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> چاوەڕێ بکە...</div>';
    resultsDiv.style.display = 'block';
    
    try {
        const response = await fetch('lineage_engine.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=search_members&query=${encodeURIComponent(query)}`
        });
        const data = await response.json();
        
        if (data.success && data.members.length > 0) {
            resultsDiv.innerHTML = '';
            data.members.forEach(m => {
                resultsDiv.innerHTML += `
                    <div class="candidate-item" style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer;" onclick='selectManualBridge(${JSON.stringify(m).replace(/'/g, "&apos;")})'>
                        <div style="font-weight: bold;">${m.full_name}</div>
                        <div style="font-size: 0.75rem; color: #666;">باوک: ${m.father_name || '---'} | کۆد: ${m.member_code || '---'}</div>
                    </div>
                `;
            });
        } else {
            resultsDiv.innerHTML = '<div style="padding: 10px; color: #888; font-size: 0.85rem;">هیچ ئەندامێک نەدۆزرایەوە.</div>';
        }
    } catch (e) {
        resultsDiv.innerHTML = '<div style="padding: 10px; color: red;">هەڵەیەک لە گەڕان ڕوویدا</div>';
    }
}

function selectManualBridge(member) {
    // We need to decide which chain node this bridge links to. 
    // Default to the last ancestor in the chain if not obvious.
    const chainIdx = currentRecognition.missingChain.length - 1; 
    
    currentRecognition.bridgeMember = {
        ...member,
        score: 100, // Manual selection is 100% intentional
        chainIndex: chainIdx
    };
    currentRecognition.bestChainIndex = chainIdx;
    
    renderBridgeCard(currentRecognition.bridgeMember);
    document.getElementById('manualSearchResults').style.display = 'none';
    document.getElementById('bridgeSection').style.display = 'block';
}

async function renderBridgeCard(member) {
    const bridgeMemberDiv = document.getElementById('bridgeMember');
    if (!bridgeMemberDiv || !member) return;
    bridgeMemberDiv.innerHTML = `
        <img src="${member.profile_image || 'assets/default_avatar.png'}" alt="Photo" onerror="this.src='assets/default_avatar.png'">
        <div style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: bold; color: var(--maroon);">${member.full_name || '---'}</div>
                <span class="badge" style="background: #4caf50; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">${member.score || 100}% Match</span>
            </div>
            <div style="font-size: 0.8rem; color: #666;">کۆد: ${member.member_code || '---'} | باوک: ${member.father_name || '---'}</div>
            <div style="font-size: 0.75rem; color: #888;">باپیر: ${member.grandfather_name || '---'} | باپیری گەورە: ${member.great_grandfather_name || '---'}</div>
        </div>
    `;
}

function renderCandidatesList(foundNodes) {
    const list = document.getElementById('candidatesList');
    list.innerHTML = '';
    
    let allCandidates = [];
    if (foundNodes) {
        const entries = Array.isArray(foundNodes) ? foundNodes.entries() : Object.entries(foundNodes);
        for (const [idx, node] of entries) {
            if (node && node.candidates) {
                node.candidates.forEach(c => {
                    allCandidates.push({...c, chainIndex: parseInt(idx)});
                });
            }
        }
    }
    
    allCandidates.sort((a, b) => b.score - a.score);
    
    if (allCandidates.length === 0) {
        list.innerHTML = '<p style="text-align: center; color: #888; font-size: 0.85rem; padding: 10px;">هیچ کاندیدێکی تر نەدۆزرایەوە.</p>';
        return;
    }
    
    allCandidates.forEach(c => {
        const isCurrent = currentRecognition.bridgeMember && currentRecognition.bridgeMember.id == c.id;
        list.innerHTML += `
            <div class="candidate-item" style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; background: ${isCurrent ? '#f0f4ff' : 'white'}; border-radius: 4px; margin-bottom: 5px;" onclick='selectCandidate(${JSON.stringify(c).replace(/'/g, "&apos;")})'>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-weight: bold; font-size: 0.9rem; color: ${isCurrent ? '#1976d2' : '#333'};">${c.full_name}</div>
                    <span style="font-size: 0.75rem; font-weight: bold; color: #4caf50;">${c.score}%</span>
                </div>
                <div style="font-size: 0.75rem; color: #666;">باوک: ${c.father_name || '---'} | باپیر: ${c.grandfather_name || '---'}</div>
            </div>
        `;
    });
}

function selectCandidate(member) {
    currentRecognition.bridgeMember = member;
    currentRecognition.bestChainIndex = member.chainIndex;
    renderBridgeCard(member);
    renderCandidatesList(currentRecognition.foundNodes);
    document.getElementById('candidatesList').style.display = 'none';
}

function toggleCandidates() {
    const list = document.getElementById('candidatesList');
    list.style.display = list.style.display === 'none' ? 'block' : 'none';
}

function highlightSharedAncestors(ancestorIds, rowElem) {
    // 1. Clear any active highlights on family rows
    document.querySelectorAll('.shared-family-row').forEach(row => {
        row.classList.remove('active-highlight');
        row.style.borderColor = '#eee';
        row.style.background = '#fff';
        row.style.boxShadow = '0 1px 3px rgba(0,0,0,0.05)';
    });

    // 2. Set active state on the clicked row
    rowElem.classList.add('active-highlight');
    rowElem.style.borderColor = '#fbbf24';
    rowElem.style.background = '#fffbeb';
    rowElem.style.boxShadow = '0 0 10px rgba(251, 191, 36, 0.3)';

    // 3. Highlight the point of union (Pirkhidri Shaho - ID 28) in the recognized chain
    const chainItems = document.querySelectorAll('.chain-item');
    let firstHighlighted = null;

    chainItems.forEach(item => {
        const memberId = parseInt(item.getAttribute('data-member-id') || '0');
        if (memberId === 28) {
            item.classList.add('highlight-shared');
            firstHighlighted = item;
        } else {
            item.classList.remove('highlight-shared');
        }
    });

    // 4. Scroll to the highlighted point of union beautifully
    if (firstHighlighted) {
        firstHighlighted.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
</script>

<?php require_once 'includes/layout_end.php'; ?>
