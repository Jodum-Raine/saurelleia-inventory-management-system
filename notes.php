<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';
require_once 'templates.php';

$user_id = $_SESSION['user_id'];

// Fetch all notes for this user
$notes = [];
$stmt = $conn->prepare("SELECT id, title, content, created_at, updated_at FROM notes WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}
$stmt->close();

// Fetch profile pic
$profile_pic = null;
$stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($r = $res->fetch_assoc()) $profile_pic = $r['profile_pic'];
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Notes - InventoryMS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== NOTES PAGE — PINK THEME ===== */
        .notes-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            min-height: calc(100vh - 200px);
        }
        .notes-sidebar {
            background: white;
            border-radius: 24px;
            padding: 20px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
            display: flex;
            flex-direction: column;
            max-height: 78vh;
        }
        .notes-sidebar h2 {
            color: #831843;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .new-note-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
            font-family: inherit;
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
            transition: all 0.3s ease;
        }
        .new-note-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
        }
        .notes-search {
            position: relative;
            margin-bottom: 16px;
        }
        .notes-search i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9d174d;
            opacity: 0.5;
            font-size: 13px;
        }
        .notes-search input {
            width: 100%;
            padding: 10px 12px 10px 34px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 8px;
            color: #831843;
            font-size: 13px;
            font-family: inherit;
        }
        .notes-search input:focus {
            outline: none;
            border-color: #ec4899;
            background: white;
        }
        .notes-list {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .note-item {
            padding: 12px 14px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .note-item:hover {
            background: #fce7f3;
            border-color: rgba(236, 72, 153, 0.25);
            transform: translateX(3px);
        }
        .note-item.active {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.15) 0%, rgba(190, 24, 93, 0.08) 100%);
            border-color: #ec4899;
        }
        .note-item-title {
            color: #831843;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .note-item-title i {
            color: #ec4899;
            font-size: 12px;
        }
        .note-item-date {
            color: #9d174d;
            font-size: 11px;
            opacity: 0.7;
        }
        .notes-empty {
            text-align: center;
            color: #9d174d;
            opacity: 0.6;
            padding: 30px 10px;
            font-size: 13px;
        }
        .notes-editor {
            background: white;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid rgba(236, 72, 153, 0.15);
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.08);
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }
        .editor-toolbar {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        .editor-toolbar select {
            padding: 8px 12px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 8px;
            color: #831843;
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            flex: 1;
            min-width: 200px;
        }
        .editor-toolbar select:focus {
            outline: none;
            border-color: #ec4899;
            background: white;
        }
        .toolbar-btn {
            padding: 8px 14px;
            background: #fce7f3;
            border: none;
            border-radius: 8px;
            color: #831843;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: inherit;
        }
        .toolbar-btn:hover {
            background: #fbcfe8;
        }
        .toolbar-btn.primary {
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(236, 72, 153, 0.25);
        }
        .toolbar-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(236, 72, 153, 0.35);
        }
        .toolbar-btn.danger {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }
        .toolbar-btn.danger:hover {
            background: #dc2626;
            color: white;
        }
        .title-input {
            width: 100%;
            padding: 14px 16px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 10px;
            color: #831843;
            font-size: 18px;
            font-weight: 700;
            font-family: inherit;
            margin-bottom: 12px;
        }
        .title-input:focus {
            outline: none;
            border-color: #ec4899;
            background: white;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        .content-input {
            width: 100%;
            flex: 1;
            padding: 16px;
            background: #fff5f7;
            border: 1px solid rgba(236, 72, 153, 0.2);
            border-radius: 10px;
            color: #831843;
            font-size: 14px;
            line-height: 1.7;
            font-family: 'Courier New', monospace;
            resize: vertical;
            min-height: 400px;
        }
        .content-input:focus {
            outline: none;
            border-color: #ec4899;
            background: white;
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        .editor-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9d174d;
            opacity: 0.5;
            text-align: center;
        }
        .editor-empty i {
            font-size: 64px;
            color: #fbcfe8;
            margin-bottom: 16px;
        }
        .editor-empty h3 {
            color: #9d174d;
            font-size: 18px;
            margin-bottom: 8px;
        }
        @media (max-width: 968px) {
            .notes-wrapper {
                grid-template-columns: 1fr;
            }
            .notes-sidebar {
                max-height: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="modern-sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-boxes"></i></div>
            <div class="logo-text"><h3>InventoryMS</h3><p>v2.0</p></div>
        </div>
        <a href="profile.php" style="text-decoration: none;">
            <div class="sidebar-user">
                <div class="user-avatar-large">
                    <?php if ($profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['user'], 0, 2)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <h4><?php echo htmlspecialchars($_SESSION['user']); ?></h4>
                    <p><?php echo isset($_SESSION['role']) && $_SESSION['role'] == 'admin' ? 'Administrator' : 'Staff Member'; ?></p>
                </div>
            </div>
        </a>
        <nav class="sidebar-nav">
            <a href="index.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="sales.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Sales</span></a>
            <a href="analytics.php" class="nav-item"><i class="fas fa-chart-line"></i><span>Analytics</span></a>
            <a href="notes.php" class="nav-item active"><i class="fas fa-sticky-note"></i><span>Notes</span></a>
            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'){ ?>
                <a href="admin_add_users.php" class="nav-item"><i class="fas fa-users"></i><span>User Management</span></a>
            <?php } ?>
            <a href="profile.php" class="nav-item"><i class="fas fa-user-circle"></i><span>My Profile</span></a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </div>

    <div class="modern-main">
        <div class="welcome-banner">
            <div class="banner-content">
                <h1>Notes</h1>
                <p>Save templates, reminders, and anything you need. Fully editable and removable.</p>
            </div>
            <div class="banner-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('l'); ?></div>
                    <div class="stat-label"><?php echo date('F j, Y'); ?></div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.3); color: #dc2626; padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="notes-wrapper">
            <!-- Left: Notes List -->
            <div class="notes-sidebar">
                <h2><i class="fas fa-book"></i> My Notes</h2>
                <button class="new-note-btn" onclick="newNote()">
                    <i class="fas fa-plus"></i> New Note
                </button>
                <div class="notes-search">
                    <i class="fas fa-search"></i>
                    <input type="text" id="noteSearch" placeholder="Search notes..." onkeyup="filterNotes()">
                </div>
                <div class="notes-list" id="notesList">
                    <?php if (count($notes) > 0): ?>
                        <?php foreach ($notes as $note): ?>
                            <div class="note-item" 
                                 data-id="<?php echo $note['id']; ?>"
                                 data-title="<?php echo htmlspecialchars(strtolower($note['title'])); ?>"
                                 onclick="loadNote(<?php echo $note['id']; ?>, this)">
                                <div class="note-item-title">
                                    <i class="fas fa-sticky-note"></i>
                                    <?php echo htmlspecialchars($note['title']); ?>
                                </div>
                                <div class="note-item-date">
                                    <i class="fas fa-clock"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($note['updated_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notes-empty">
                            <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 8px;"></i>
                            No notes yet.<br>Click "New Note" to start!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Editor -->
            <div class="notes-editor">
                <div id="editorEmpty" class="editor-empty">
                    <i class="fas fa-feather-alt"></i>
                    <h3>No note selected</h3>
                    <p>Choose a note on the left or click "New Note" to start writing.</p>
                </div>

                <div id="editorActive" style="display:none; flex-direction: column; flex: 1;">
                    <input type="hidden" id="noteId" value="">
                    <input type="text" id="noteTitle" class="title-input" placeholder="Note title..." maxlength="255">
                    <div class="editor-toolbar">
                        <select id="templateSelect" onchange="applyTemplate()">
                            <option value="">📋 Insert a template...</option>
                            <?php foreach ($NOTE_TEMPLATES as $name => $content): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>" data-content="<?php echo htmlspecialchars($content); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="toolbar-btn primary" onclick="saveNote()">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <button class="toolbar-btn danger" id="deleteBtn" onclick="deleteNote()" style="display:none;">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                    <textarea id="noteContent" class="content-input" placeholder="Write your note here..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentNoteId = null;

        // ===== Templates data (from PHP) =====
        const TEMPLATES = <?php echo json_encode($NOTE_TEMPLATES); ?>;

        function newNote() {
            currentNoteId = null;
            document.getElementById('editorEmpty').style.display = 'none';
            document.getElementById('editorActive').style.display = 'flex';
            document.getElementById('noteId').value = '';
            document.getElementById('noteTitle').value = '';
            document.getElementById('noteContent').value = '';
            document.getElementById('deleteBtn').style.display = 'none';
            document.getElementById('noteTitle').focus();
        }

        function loadNote(id, el) {
            // Fetch note content from server
            fetch('get_note.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    currentNoteId = data.id;
                    document.getElementById('editorEmpty').style.display = 'none';
                    document.getElementById('editorActive').style.display = 'flex';
                    document.getElementById('noteId').value = data.id;
                    document.getElementById('noteTitle').value = data.title;
                    document.getElementById('noteContent').value = data.content;
                    document.getElementById('deleteBtn').style.display = 'inline-flex';

                    // Highlight selected
                    document.querySelectorAll('.note-item').forEach(n => n.classList.remove('active'));
                    el.classList.add('active');
                })
                .catch(() => alert('Failed to load note'));
        }

        function applyTemplate() {
            const sel = document.getElementById('templateSelect');
            const name = sel.value;
            if (!name) return;

            const content = TEMPLATES[name] || '';

            // If title empty, auto-fill with template name
            const titleField = document.getElementById('noteTitle');
            if (!titleField.value.trim()) {
                titleField.value = name;
            }

            const textarea = document.getElementById('noteContent');
            if (textarea.value.trim() && !confirm('Replace current content with this template?')) {
                sel.value = '';
                return;
            }
            textarea.value = content;
            sel.value = '';
        }

        function saveNote() {
            const id = document.getElementById('noteId').value;
            const title = document.getElementById('noteTitle').value.trim();
            const content = document.getElementById('noteContent').value;

            if (!title) {
                alert('Please add a title');
                return;
            }

            const formData = new FormData();
            if (id) formData.append('id', id);
            formData.append('title', title);
            formData.append('content', content);

            fetch('save_note.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show updated list
                    window.location.href = 'notes.php?highlight=' + data.id + '&saved=1';
                } else {
                    alert('Error: ' + (data.error || 'Save failed'));
                }
            })
            .catch(() => alert('Error saving note'));
        }

        function deleteNote() {
            const id = document.getElementById('noteId').value;
            if (!id) return;
            if (!confirm('Delete this note? This cannot be undone.')) return;

            fetch('delete_note.php?id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'notes.php?deleted=1';
                    } else {
                        alert('Error: ' + (data.error || 'Delete failed'));
                    }
                })
                .catch(() => alert('Error deleting note'));
        }

        function filterNotes() {
            const term = document.getElementById('noteSearch').value.toLowerCase();
            document.querySelectorAll('.note-item').forEach(item => {
                const title = item.getAttribute('data-title');
                item.style.display = title.includes(term) ? '' : 'none';
            });
        }

        // Auto-load note if URL has ?highlight=ID
        window.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('highlight');
            if (id) {
                const el = document.querySelector('.note-item[data-id="' + id + '"]');
                if (el) loadNote(id, el);
            }
            if (params.get('saved')) {
                // Optional: could show toast
            }
        });
    </script>
</body>
</html>