<?php
// Dashboard de estado de servicios críticos.
// Lee de MariaDB las métricas que envía Telegraf y permite iniciar, parar,
// reiniciar o consultar servicios del cliente por SSH.

require __DIR__ . '/config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Erro de conexión coa base de datos");
}

$conn->query("SET NAMES utf8");

$cliente_ip = CLIENTE_IP;

$servizos_permitidos = [
    "sssd.service",
    "ssh.service",
    "cron.service",
    "ufw.service",
    "telegraf.service"
];

$accions_validas = ["start", "stop", "restart", "status"];

$mensaxe = "";

if (isset($_GET['servizo']) && isset($_GET['accion'])) {
    $servizo = $_GET['servizo'];
    $accion  = $_GET['accion'];

    // Lista blanca: solo se ejecutan servicios y acciones conocidos
    if (in_array($servizo, $servizos_permitidos, true) &&
        in_array($accion, $accions_validas, true)) {

        $cmd = "ssh -o StrictHostKeyChecking=no " . SSH_USER . "@" . $cliente_ip .
               " 'sudo systemctl " . $accion . " " . $servizo . "' 2>&1";

        $output = shell_exec($cmd);

        $mensaxe = "
        <div class='msg'>
            <b>Acción:</b> " . htmlspecialchars($accion) . "<br>
            <b>Servizo:</b> " . htmlspecialchars($servizo) . "<br><br>
            <pre>" . htmlspecialchars((string)$output) . "</pre>
        </div>";
    } else {
        $mensaxe = "<div class='error'>Acción ou servizo non permitido</div>";
    }
}

// Último estado registrado de cada servicio
$sql = "
    SELECT s1.name, s1.active, s1.sub, s1.timestamp
    FROM servizos_criticos s1
    INNER JOIN (
        SELECT name, MAX(timestamp) AS max_ts
        FROM servizos_criticos
        GROUP BY name
    ) s2
    ON s1.name = s2.name AND s1.timestamp = s2.max_ts
    ORDER BY s1.name ASC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="gl">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="5">
<title>Dashboard ASIR</title>
<style>
body {
    margin: 0;
    padding: 30px;
    background: #0f172a;
    color: white;
    font-family: Arial, Helvetica, sans-serif;
}
h1 { text-align: center; margin-bottom: 10px; }
.info { text-align: center; color: #cbd5e1; margin-bottom: 30px; }
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}
.card {
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.4);
}
.ok   { background: #16a34a; }
.fail { background: #dc2626; }
.nome { font-size: 24px; font-weight: bold; margin-bottom: 15px; }
.estado { font-size: 18px; margin-bottom: 10px; }
.sub { margin-bottom: 10px; }
.time { font-size: 14px; opacity: 0.9; margin-bottom: 20px; }
.actions a {
    display: inline-block;
    margin: 4px;
    padding: 8px 12px;
    background: white;
    color: black;
    text-decoration: none;
    border-radius: 6px;
    font-weight: bold;
}
.actions a:hover { background: #ddd; }
.msg {
    background: #1e293b;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
.error {
    background: #7f1d1d;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
pre {
    overflow: auto;
    background: black;
    color: #00ff00;
    padding: 10px;
    border-radius: 6px;
}
.footer { margin-top: 40px; text-align: center; color: #94a3b8; }
</style>
</head>
<body>

<h1>ESTADO SERVIZOS</h1>

<div class="info">
    Cliente monitorizado: <b><?php echo htmlspecialchars($cliente_ip); ?></b><br>
    Última actualización: <b><?php echo date("H:i:s"); ?></b>
</div>

<?php echo $mensaxe; ?>

<div class="grid">
<?php
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $estado_ok = ($row['active'] == 'active');
        $clase = $estado_ok ? "ok" : "fail";
        $nome = urlencode($row['name']);

        echo "<div class='card $clase'>";
        echo "<div class='nome'>" . htmlspecialchars($row['name']) . "</div>";
        echo "<div class='estado'>" . ($estado_ok ? "ACTIVO" : "FALLO") . "</div>";
        echo "<div class='sub'>Subestado: " . htmlspecialchars($row['sub']) . "</div>";
        echo "<div class='time'>Último rexistro:<br>" . htmlspecialchars($row['timestamp']) . "</div>";
        echo "<div class='actions'>";
        echo "<a href='?servizo=$nome&accion=start'>START</a>";
        echo "<a href='?servizo=$nome&accion=stop'>STOP</a>";
        echo "<a href='?servizo=$nome&accion=restart'>RESTART</a>";
        echo "<a href='?servizo=$nome&accion=status'>STATUS</a>";
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "Non hai datos dispoñibles";
}

$conn->close();
?>
</div>

<div class="footer">David Ruso Quintáns, todos os dereitos reservados</div>

</body>
</html>
