<?php
declare(strict_types=1);
header_remove('X-Powered-By');

function getIcon(string $ext): string
{
    return match ($ext) {
        'pdf' => 'bi-file-earmark-pdf text-danger',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico' => 'bi-file-image text-primary',
        'doc', 'docx' => 'bi-file-earmark-word text-info',
        'xls', 'xlsx', 'csv' => 'bi-file-earmark-excel text-success',
        'ppt', 'pptx' => 'bi-file-earmark-ppt text-warning',
        'zip', 'rar', '7z', 'tar', 'gz', 'iso' => 'bi-file-earmark-zip text-secondary',
        'mp4', 'mov', 'avi', 'mkv', 'webm' => 'bi-file-earmark-play text-danger',
        'mp3', 'wav', 'ogg' => 'bi-file-earmark-music text-info',
        'php' => 'bi-filetype-php text-primary',
        'html', 'htm' => 'bi-filetype-html text-warning',
        'js', 'json' => 'bi-filetype-js text-warning',
        'css', 'scss' => 'bi-filetype-css text-primary',
        'sql' => 'bi-database text-secondary',
        'txt', 'md', 'log', 'ini', 'conf' => 'bi-file-text text-light',
        'exe', 'msi', 'dll', 'bin', 'dat' => 'bi-file-earmark-binary text-secondary',
        default => 'bi-file-earmark text-muted'
    };
}

function json_out(int $code, array $payload)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$path = $_GET['path'] ?? '';
$path = preg_replace('#^(\./|/)+#', '', $path);
$path = str_replace('..', '', $path);

$baseDir = realpath(__DIR__);
$currentDir = realpath($baseDir . '/' . $path);

if (!$currentDir || !str_starts_with($currentDir, $baseDir)) {
    http_response_code(403);
    exit('Acesso negado.');
}

/* ===== AÇÕES (BACKEND) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fileRel = $_POST['file'] ?? '';
    $newName = $_POST['newName'] ?? '';

    if ($fileRel === '') json_out(400, ['ok' => false, 'msg' => 'Arquivo/pasta não informada']);

    $realFile = realpath($baseDir . '/' . ltrim($fileRel, '/'));
    
    if (!$realFile || !str_starts_with($realFile, $baseDir)) {
        json_out(403, ['ok' => false, 'msg' => 'Acesso negado ou arquivo não encontrado.']);
    }

    // --- CHECK: PERMISSÃO DE ESCRITA ---
    if (!is_writable($realFile) && $action !== 'rename') { 
        json_out(500, ['ok' => false, 'msg' => 'Permissão negada pelo Sistema Operacional (chmod).']);
    }

    // --- AÇÃO: EXCLUIR ---
    if ($action === 'delete') {
        clearstatcache(true, $realFile);

        if (is_dir($realFile)) {
            $it = new RecursiveDirectoryIterator($realFile, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                if ($f->isDir()) {
                    if (!@rmdir($f->getRealPath())) {
                        $error = error_get_last()['message'] ?? 'Erro desconhecido';
                        json_out(500, ['ok' => false, 'msg' => "Falha ao excluir pasta interna: $error"]);
                    }
                } else {
                    if (!@unlink($f->getRealPath())) {
                        $error = error_get_last()['message'] ?? 'Erro desconhecido';
                        json_out(500, ['ok' => false, 'msg' => "Falha ao excluir arquivo interno: $error"]);
                    }
                }
            }
            if (!@rmdir($realFile)) {
                $error = error_get_last()['message'] ?? 'Erro de permissão';
                json_out(500, ['ok' => false, 'msg' => "Falha ao excluir diretório raiz: $error"]);
            }
            json_out(200, ['ok' => true]);
        } elseif (is_file($realFile)) {
            if (!@unlink($realFile)) {
                $error = error_get_last()['message'] ?? 'Erro de permissão';
                json_out(500, ['ok' => false, 'msg' => "Erro no sistema: $error"]);
            }
            json_out(200, ['ok' => true]);
        }
    }

    // --- AÇÃO: RENOMEAR ---
    if ($action === 'rename') {
        $dir = dirname($realFile);
        
        if (!is_writable($dir)) {
            json_out(500, ['ok' => false, 'msg' => 'Sem permissão de escrita na pasta pai. Rode: sudo chmod -R 777 ' . $dir]);
        }

        $safe = trim($newName);
        $safe = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '', $safe);

        if ($safe === '') json_out(400, ['ok' => false, 'msg' => 'Nome inválido']);
        
        $dest = $dir . '/' . $safe;
        
        if (file_exists($dest)) {
            json_out(409, ['ok' => false, 'msg' => 'Já existe um item com esse nome.']);
        }

        if (!@rename($realFile, $dest)) {
            $error = error_get_last()['message'] ?? 'Motivo desconhecido';
            if (str_contains($error, 'Permission denied')) {
                $error = "Permissão negada! Tente rodar no terminal: sudo chmod -R 777 $dir";
            }
            json_out(500, ['ok' => false, 'msg' => $error]);
        }
        json_out(200, ['ok' => true]);
    }

    json_out(400, ['ok' => false, 'msg' => 'Ação inválida']);
}

/* ===== LISTAGEM ===== */
$folders = $files = [];
foreach (scandir($currentDir) ?: [] as $item) {
    if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;
    $full = $currentDir . '/' . $item;
    $isWritable = is_writable($full); 
    
    $data = ['name' => $item, 'writable' => $isWritable];
    
    is_dir($full) ? $folders[] = $data : $files[] = $data;
}
usort($folders, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Explorer Pro</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-body: #0f172a;
            --bg-card: rgba(30, 41, 59, 0.7);
            --bg-header: rgba(15, 23, 42, 0.85);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-color: #3b82f6;
            --danger-color: #ef4444;
            --folder-color: #fbbf24;
            --card-radius: 16px;
        }

        [data-theme="light"] {
            --bg-body: #f1f5f9;
            --bg-card: rgba(255, 255, 255, 0.8);
            --bg-header: rgba(255, 255, 255, 0.9);
            --border-color: rgba(0, 0, 0, 0.05);
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --accent-color: #2563eb;
            --folder-color: #f59e0b;
        }

        body {
            background-color: var(--bg-body);
            background-image: radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                              radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            transition: .3s;
        }

        .app-header {
            background: var(--bg-header);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 1rem 0;
        }

        .card-file {
            background: var(--bg-card);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border-color);
            border-radius: var(--card-radius);
            padding: 1.25rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .card-file:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            border-color: var(--accent-color);
        }

        .file-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
            transition: transform 0.3s;
        }
        
        .card-file:hover .file-icon { transform: scale(1.1); }

        .file-name {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--text-primary);
            width: 100%;
            text-align: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .card-file.locked { opacity: 0.6; border-style: dashed; }
        .lock-badge {
            position: absolute; top: 10px; left: 10px;
            color: var(--danger-color); font-size: 1.2rem;
            background: rgba(0,0,0,0.2); border-radius: 50%;
            width: 30px; height: 30px;
            display: flex; align-items: center; justify-content: center;
        }

        .file-actions {
            margin-top: 1rem; width: 100%;
            display: flex; gap: 0.5rem; justify-content: center;
            opacity: 0.7; transition: opacity 0.2s;
        }
        .card-file:hover .file-actions { opacity: 1; }

        .btn-action {
            width: 32px; height: 32px; padding: 0;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; border: none;
            background: rgba(255,255,255,0.05); color: var(--text-secondary);
            transition: all 0.2s;
        }
        .btn-action:hover:not(:disabled) { color: #fff; transform: scale(1.1); background: var(--accent-color); }
        .btn-action:disabled { cursor: not-allowed; opacity: 0.3; }
        .btn-action.delete:hover:not(:disabled) { background: var(--danger-color); }

        .btn-open-folder {
            margin-top: 1rem; width: 100%; border-radius: 8px; font-size: 0.85rem;
            background: rgba(59, 130, 246, 0.1); color: var(--accent-color);
            border: 1px solid transparent;
        }
        .btn-open-folder:hover { background: var(--accent-color); color: white; }

        .clock {
            font-family: monospace; font-weight: 700; color: var(--accent-color);
            background: rgba(59, 130, 246, 0.1); padding: 4px 10px; border-radius: 6px;
        }
        
        #themeBtn {
            width: 40px; height: 40px; border-radius: 50%;
            border: 1px solid var(--border-color); background: transparent;
            color: var(--text-primary); display: flex; align-items: center; justify-content: center;
        }

        .modal-content { background: var(--bg-body); border: 1px solid var(--border-color); }
        .dropdown-menu { background: var(--bg-card); backdrop-filter: blur(10px); border: 1px solid var(--border-color); }
        .dropdown-item { color: var(--text-primary); }
        .dropdown-item:hover { background: rgba(255,255,255,0.05); }
    </style>
</head>
<body>

<header class="app-header">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <h4 class="m-0 fw-bold"><i class="bi bi-hdd-network me-2"></i>Explorer</h4>
            <?php if(!empty($path)): ?>
                <div class="vr d-none d-md-block mx-2 text-secondary"></div>
                <span class="d-none d-md-block text-secondary text-truncate" style="max-width: 300px;">
                    /<?= htmlspecialchars($path) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="clock d-none d-sm-block" id="clock">--:--:--</span>
            <button id="themeBtn"><i class="bi bi-moon-stars"></i></button>
        </div>
    </div>
</header>

<div class="container my-5">
    <?php if(!empty($path)): 
        $parent = dirname($path);
        if($parent === '.') $parent = '';
    ?>
    <div class="mb-4">
        <a href="?path=<?= urlencode($parent) ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
    </div>
    <?php endif; ?>

    <?php if (empty($folders) && empty($files)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-folder-x display-1 opacity-25"></i>
            <p class="mt-3">Pasta vazia</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($folders)): ?>
        <h6 class="text-uppercase text-secondary fw-bold mb-3 fs-7"><i class="bi bi-folder2 me-1"></i> Pastas</h6>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4 mb-5">
            <?php foreach ($folders as $item):
                $folder = $item['name'];
                $isWritable = $item['writable'];
                $rel = trim($path . '/' . $folder, '/');
                $folderFull = $currentDir . '/' . $folder;
                $indexFile = (file_exists("$folderFull/index.php") || file_exists("$folderFull/index.html")) ? $rel . '/' : null;
                ?>
                <div class="col">
                    <div class="card-file <?= !$isWritable ? 'locked' : '' ?>">
                        <?php if(!$isWritable): ?>
                            <div class="lock-badge" title="Sem permissão de escrita"><i class="bi bi-lock-fill"></i></div>
                        <?php endif; ?>

                        <div class="dropdown position-absolute top-0 end-0 m-2">
                            <button class="btn btn-sm btn-link text-secondary p-0 px-2 text-decoration-none" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <?php if($isWritable): ?>
                                    <button class="dropdown-item btn-rename-folder" data-folder="<?= htmlspecialchars($rel) ?>">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Renomear
                                    </button>
                                    <?php else: ?>
                                    <span class="dropdown-item text-muted"><i class="bi bi-lock me-2"></i>Renomear (Bloqueado)</span>
                                    <?php endif; ?>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <?php if($isWritable): ?>
                                    <button class="dropdown-item btn-delete-folder text-danger" data-folder="<?= htmlspecialchars($rel) ?>">
                                        <i class="bi bi-trash me-2"></i>Excluir
                                    </button>
                                    <?php else: ?>
                                    <span class="dropdown-item text-muted"><i class="bi bi-lock me-2"></i>Excluir (Bloqueado)</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                        
                        <i class="bi bi-folder-fill file-icon" style="color: var(--folder-color);"></i>
                        <div class="file-name" title="<?= htmlspecialchars($folder) ?>"><?= htmlspecialchars($folder) ?></div>
                        
                        <a href="<?= $indexFile ? "/".htmlspecialchars($indexFile) : "?path=".urlencode($rel) ?>" 
                           target="<?= $indexFile ? '_blank' : '_self' ?>"
                           class="btn btn-open-folder btn-sm mt-auto">
                           <i class="bi <?= $indexFile ? 'bi-play-circle' : 'bi-box-arrow-in-right' ?> me-1"></i> 
                           <?= $indexFile ? 'Abrir App' : 'Abrir' ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($files)): ?>
        <h6 class="text-uppercase text-secondary fw-bold mb-3 fs-7"><i class="bi bi-files me-1"></i> Arquivos</h6>
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-4">
            <?php foreach ($files as $item):
                $file = $item['name'];
                $isWritable = $item['writable'];
                $rel = trim($path . '/' . $file, '/');
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $icon = getIcon($ext);
                ?>
                <div class="col">
                    <div class="card-file <?= !$isWritable ? 'locked' : '' ?>">
                        <?php if(!$isWritable): ?>
                            <div class="lock-badge" title="Somente Leitura"><i class="bi bi-lock-fill"></i></div>
                        <?php endif; ?>

                        <i class="bi <?= $icon ?> file-icon"></i>
                        <div class="file-name" title="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></div>
                        
                        <div class="file-actions">
                            <button class="btn-action view btn-view" data-file="<?= $rel ?>" data-ext="<?= $ext ?>" title="Visualizar">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-action edit btn-rename" data-file="<?= $rel ?>" <?= !$isWritable ? 'disabled' : '' ?>>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="/<?= $rel ?>" download class="btn-action download" title="Baixar">
                                <i class="bi bi-download"></i>
                            </a>
                            <button class="btn-action delete btn-delete" data-file="<?= $rel ?>" <?= !$isWritable ? 'disabled' : '' ?>>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Visualizar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="viewerContainer" style="min-height: 400px; background: #000;"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" id="openInNewTab">Nova Aba</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const themeBtn = document.getElementById('themeBtn');
    
    function applyTheme(t) {
        document.documentElement.setAttribute('data-theme', t);
        themeBtn.innerHTML = t === 'light' ? '<i class="bi bi-moon-stars"></i>' : '<i class="bi bi-sun"></i>';
    }

    let theme = localStorage.getItem('theme') || 'dark';
    applyTheme(theme);
    themeBtn.onclick = () => {
        theme = theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', theme);
        applyTheme(theme);
    };

    function updateClock() { document.getElementById('clock').textContent = new Date().toLocaleTimeString('pt-BR'); }
    setInterval(updateClock, 1000); updateClock();

    const modal = new bootstrap.Modal('#viewModal');
    const viewer = document.getElementById('viewerContainer');
    const openInNewTabBtn = document.getElementById('openInNewTab');
    
    function render(file, ext) {
        viewer.innerHTML = "<div class='d-flex justify-content-center align-items-center h-100 text-white'><div class='spinner-border text-primary me-2'></div> Carregando...</div>";
        ext = ext.toLowerCase();
        const fullPath = '/' + file;
        
        // 1. IMAGENS
        if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp', 'bmp'].includes(ext)) {
            viewer.innerHTML = `<div class="h-100 d-flex justify-content-center bg-dark"><img src="${fullPath}" class="img-fluid" style="max-height:80vh"></div>`;
        } 
        // 2. VÍDEOS
        else if (['mp4', 'webm', 'mkv', 'mov', 'avi'].includes(ext)) {
            viewer.innerHTML = `<div class="h-100 bg-black d-flex justify-content-center"><video controls class="w-100" style="max-height:80vh"><source src="${fullPath}"></video></div>`;
        } 
        // 3. ÁUDIOS
        else if (['mp3', 'wav', 'ogg'].includes(ext)) {
            viewer.innerHTML = `<div class="h-100 d-flex justify-content-center align-items-center bg-dark"><audio controls><source src="${fullPath}"></audio></div>`;
        } 
        // 4. PDF
        else if (['pdf'].includes(ext)) {
            viewer.innerHTML = `<embed src="${fullPath}" type="application/pdf" width="100%" height="600px">`;
        } 
        // 5. ARQUIVOS BINÁRIOS/COMPACTADOS (A PROTEÇÃO DO TRAVAMENTO)
        else if (['zip', 'rar', '7z', 'tar', 'gz', 'iso', 'exe', 'msi', 'dll', 'bin', 'dat'].includes(ext)) {
            viewer.innerHTML = `
                <div class="d-flex flex-column justify-content-center align-items-center h-100 text-white p-5">
                    <i class="bi bi-file-earmark-zip display-1 text-secondary mb-3"></i>
                    <h4>Arquivo Binário / Compactado</h4>
                    <p class="text-secondary">Este tipo de arquivo não pode ser visualizado no navegador.</p>
                    <a href="${fullPath}" download class="btn btn-primary mt-3"><i class="bi bi-download me-2"></i>Baixar Arquivo</a>
                </div>`;
        } 
        // 6. CÓDIGO/TEXTO (Default)
        else {
            fetch(fullPath).then(r => r.text()).then(t => {
                viewer.innerHTML = `<pre class="p-3 text-light m-0" style="white-space:pre-wrap;">${t.replace(/</g,'&lt;')}</pre>`;
            }).catch(()=>viewer.innerHTML="Erro ao carregar");
        }
    }

    document.querySelectorAll('.btn-view').forEach(b => b.onclick = () => {
        const f = b.dataset.file;
        openInNewTabBtn.onclick = () => window.open('/' + f, '_blank');
        modal.show();
        render(f, b.dataset.ext);
    });

    async function postAction(p) {
        const r = await fetch(location.pathname + location.search, {
            method: 'POST',
            body: new URLSearchParams(p),
            headers: {Accept: 'application/json'}
        });
        return await r.json();
    }

    function reloadOk(msg) {
        Swal.fire({
            icon: 'success', title: msg, timer: 1000, showConfirmButton: false,
            background: getComputedStyle(document.body).getPropertyValue('--bg-card'),
            color: getComputedStyle(document.body).getPropertyValue('--text-primary')
        }).then(() => location.reload());
    }

    const swalConfig = () => ({
        background: getComputedStyle(document.body).getPropertyValue('--bg-card'),
        color: getComputedStyle(document.body).getPropertyValue('--text-primary'),
        confirmButtonColor: getComputedStyle(document.body).getPropertyValue('--accent-color'),
        cancelButtonColor: getComputedStyle(document.body).getPropertyValue('--danger-color'),
    });

    document.querySelectorAll('.btn-delete').forEach(b => b.onclick = async () => {
        if(b.hasAttribute('disabled')) return;
        const f = b.dataset.file;
        const a = await Swal.fire({...swalConfig(), title: 'Excluir?', text: f, icon: 'warning', showCancelButton: true});
        if (!a.isConfirmed) return;
        const r = await postAction({action: 'delete', file: f});
        r.ok ? reloadOk('Excluído') : Swal.fire({...swalConfig(), title: 'Erro', text: r.msg, icon: 'error'});
    });

    document.querySelectorAll('.btn-rename').forEach(b => b.onclick = async () => {
        if(b.hasAttribute('disabled')) return;
        const f = b.dataset.file;
        const a = await Swal.fire({...swalConfig(), title: 'Renomear', input: 'text', inputValue: f.split('/').pop(), showCancelButton: true});
        if (!a.isConfirmed) return;
        const r = await postAction({action: 'rename', file: f, newName: a.value.trim()});
        r.ok ? reloadOk('Renomeado') : Swal.fire({...swalConfig(), title: 'Erro', text: r.msg, icon: 'error'});
    });

    document.querySelectorAll('.btn-rename-folder').forEach(b => b.onclick = async () => {
        const f = b.dataset.folder;
        const a = await Swal.fire({...swalConfig(), title: 'Renomear Pasta', input: 'text', inputValue: f.split('/').pop(), showCancelButton: true});
        if (!a.isConfirmed) return;
        const r = await postAction({action: 'rename', file: f, newName: a.value.trim()});
        r.ok ? reloadOk('Renomeado') : Swal.fire({...swalConfig(), title: 'Erro', text: r.msg, icon: 'error'});
    });
    
    document.querySelectorAll('.btn-delete-folder').forEach(b => b.onclick = async () => {
        const f = b.dataset.folder;
        const a = await Swal.fire({...swalConfig(), title: 'Excluir pasta?', text: 'Tudo será apagado.', icon: 'warning', showCancelButton: true});
        if (!a.isConfirmed) return;
        const r = await postAction({action: 'delete', file: f});
        r.ok ? reloadOk('Excluído') : Swal.fire({...swalConfig(), title: 'Erro', text: r.msg, icon: 'error'});
    });
</script>
</body>
</html>
