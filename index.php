<?php
/***** Config connexion *****/
const DB_HOST = '127.0.0.1';
const DB_NAME = 'nomdelabasededonnees';
const DB_USER = 'utilisateur';
const DB_PASS = 'motdepasse';

/***** Connexion PDO *****/
$dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
  $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (Exception $e) {
  http_response_code(500);
  echo "Erreur de connexion BD.";
  exit;
}

/***** Helpers *****/
function h($str){ return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
$allowedCats = ['maison','boulot','perso'];
$allowedStatus = ['a_faire','en_cours','termine'];
$statusLabel = ['a_faire'=>'À faire','en_cours'=>'En cours','termine'=>'Terminé'];

/***** Récupération du tri *****/
$order = ($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

/***** Handle Add *****/
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='add') {
  $title = trim($_POST['title'] ?? '');
  $category = $_POST['category'] ?? '';
  $status = $_POST['status'] ?? 'a_faire';

  if ($title !== '' && in_array($category, $allowedCats, true) && in_array($status, $allowedStatus, true)) {
    $stmt = $pdo->prepare("INSERT INTO tasks (title, category, status) VALUES (?, ?, ?)");
    $stmt->execute([$title, $category, $status]);
    header('Location: '.$_SERVER['PHP_SELF'].'?order='.$order); exit;
  } else {
    $error = "Vérifie le titre, la catégorie et l’état.";
  }
}

/***** Handle Delete *****/
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='delete') {
  $id = (int)($_POST['id'] ?? 0);
  if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: '.$_SERVER['PHP_SELF'].'?order='.$order); exit;
  }
}

/***** Handle Update status *****/
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='update') {
  $id = (int)($_POST['id'] ?? 0);
  $newStatus = $_POST['status'] ?? '';
  if ($id > 0 && in_array($newStatus, $allowedStatus, true)) {
    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    header('Location: '.$_SERVER['PHP_SELF'].'?order='.$order); exit;
  }
}

/***** Fetch tasks grouped by category *****/
$stmt = $pdo->query("
  SELECT id, title, category, status, created_at
  FROM tasks
  ORDER BY FIELD(category,'maison','boulot','perso'), created_at $order
");
$tasks = $stmt->fetchAll();

/***** Regrouper par catégorie *****/
$byCat = ['maison'=>[], 'boulot'=>[], 'perso'=>[]];
foreach ($tasks as $t) {
  $byCat[$t['category']][] = $t;
}

$catLabel = ['maison'=>'Maison','boulot'=>'Boulot','perso'=>'Perso'];
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Mes tâches</title>
  <style>
    body{background:#0f172a;color:#e5e7eb;font-family:sans-serif;margin:0;padding:0}
    .container{max-width:980px;margin:32px auto;padding:0 16px}
    h1{font-size:28px;margin:0 0 8px}
    .muted{color:#9ca3af;margin-bottom:24px}
    .grid{display:grid;gap:16px}
    @media (min-width:900px){ .grid{grid-template-columns:1fr 1fr 1fr} }
    .card{background:#111827;border:1px solid #374151;border-radius:16px;padding:12px}
    .card h2{margin:4px 0 8px;font-size:18px}
    ul{list-style:none;margin:0;padding:0}
    .task{display:flex;align-items:center;gap:12px;border:1px solid #374151;background:#0b1220;border-radius:12px;padding:10px 12px;margin-bottom:10px}
    .grow{flex:1}
    .title{font-weight:600}
    .meta{font-size:12px;color:#9ca3af}
    .chip{font-size:12px;padding:4px 8px;border-radius:999px;border:1px solid #374151;background:#1f2937}
    .state-a_faire{background:rgba(239,68,68,.15);border-color:#ef4444;color:#fecaca}
    .state-en_cours{background:rgba(217,119,6,.15);border-color:#d97706;color:#fde68a}
    .state-termine{background:rgba(22,163,74,.15);border-color:#16a34a;color:#bbf7d0}
    .actions{display:flex;gap:8px}
    button, select{
      border:none;border-radius:8px;padding:6px 10px;cursor:pointer;
      background:#2563eb;color:white;font-weight:600;font-size:13px
    }
    .btn-danger{background:#dc2626}
    form.inline{display:inline}
    .add{margin-top:24px;padding:16px;border:1px dashed #374151;border-radius:16px;background:#0b1220}
    .row{display:flex;flex-wrap:wrap;gap:10px}
    input[type="text"], .add select{
      background:#0a0f1c;border:1px solid #374151;color:#e5e7eb;
      padding:10px;border-radius:10px;min-width:200px
    }
    .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .toolbar a{color:#60a5fa;text-decoration:none;font-size:14px}
  </style>
</head>
<body>
  <div class="container">
    <div class="toolbar">
      <h1>Gestion des tâches</h1>
      <div>
        <a href="?order=desc" <?= $order==='desc'?'style="font-weight:bold"':'' ?>>📅 Plus récentes</a> | 
        <a href="?order=asc" <?= $order==='asc'?'style="font-weight:bold"':'' ?>>📅 Plus anciennes</a>
      </div>
    </div>
    <p class="muted">Accueil — listes par catégories, couleurs par état, ajout, suppression et changement d’état.</p>

    <?php if (!empty($error)): ?>
      <p style="background:#3b0d0d;border:1px solid #7f1d1d;color:#fecaca;padding:10px;border-radius:10px;margin-bottom:16px">
        <?= h($error) ?>
      </p>
    <?php endif; ?>

    <div class="grid">
      <?php foreach ($byCat as $catKey => $items): ?>
        <div class="card">
          <h2><?= h($catLabel[$catKey]) ?></h2>
          <?php if (empty($items)): ?>
            <p class="muted">Aucune tâche.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($items as $t): ?>
                <?php
                  $stateClass = 'state-'.$t['status'];
                  $date = (new DateTime($t['created_at']))->format('d/m/Y H:i');
                ?>
                <li class="task">
                  <span class="chip <?= h($stateClass) ?>"><?= h($statusLabel[$t['status']]) ?></span>
                  <div class="grow">
                    <div class="title"><?= h($t['title']) ?></div>
                    <div class="meta">Entrée le <?= h($date) ?></div>
                  </div>
                  <div class="actions">
                    <!-- Changement état -->
                    <form class="inline" method="post">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                      <select name="status" onchange="this.form.submit()">
                        <?php foreach ($allowedStatus as $st): ?>
                          <option value="<?= h($st) ?>" <?= $t['status']===$st?'selected':'' ?>>
                            <?= h($statusLabel[$st]) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <!-- Suppression -->
                    <form class="inline" method="post" onsubmit="return confirm('Supprimer cette tâche ?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                      <button class="btn-danger" type="submit">Supprimer</button>
                    </form>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="add">
      <h2>Ajouter une tâche</h2>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="row" style="margin-bottom:10px">
          <input type="text" name="title" placeholder="Titre de la tâche" required>
          <select name="category" required>
            <option value="" disabled selected>Catégorie</option>
            <option value="maison">Maison</option>
            <option value="boulot">Boulot</option>
            <option value="perso">Perso</option>
          </select>
          <select name="status" required>
            <option value="a_faire" selected>À faire</option>
            <option value="en_cours">En cours</option>
            <option value="termine">Terminé</option>
          </select>
          <button type="submit">Ajouter</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
