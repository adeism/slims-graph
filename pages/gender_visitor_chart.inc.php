<?php
/**
 * Plugin Name: SLiMS Graph Network Analysis
 * Plugin URI: https://github.com/erwansetyobudi/slims-graph
 * Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
 * Version: 1.0.0
 * Author: Erwan Setyo Budi
 * Author URI: https://github.com/erwansetyobudi/
 */

header("Content-Type: text/html; charset=UTF-8");

use SLiMS\DB;

$db = DB::getInstance();

if (!defined('INDEX_AUTH')) {
    die("cannot access this file directly");
} elseif (INDEX_AUTH != 1) {
    die("cannot access this file directly");
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$query = $db->prepare("
    SELECT MONTH(vc.checkin_date) AS bulan, m.gender, COUNT(*) AS jumlah
    FROM visitor_count vc
    JOIN member m ON vc.member_id = m.member_id
    WHERE YEAR(vc.checkin_date) = :year
    GROUP BY bulan, m.gender
    ORDER BY bulan ASC
    LIMIT :limit
");

$query->bindValue(':year', $year, PDO::PARAM_INT);
$query->bindValue(':limit', $limit, PDO::PARAM_INT);
$query->execute();


$bulanMap = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

$raw = [];
for ($i = 1; $i <= 12; $i++) {
    $nama = $bulanMap[$i - 1];
    $raw[$nama] = ['month' => $nama, 'female' => 0, 'male' => 0];
}

while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $bulan = (int)$row['bulan'];
    $gender = $row['gender'];
    $jumlah = (int)$row['jumlah'];

    if ($bulan < 1 || $bulan > 12) continue; // Hindari error jika bulan tidak valid

    $namaBulan = $bulanMap[$bulan - 1];

    if ($gender == 0) {
        $raw[$namaBulan]['female'] += $jumlah;
    } else {
        $raw[$namaBulan]['male'] += $jumlah;
    }
}


$chartData = array_values($raw);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Gender Visitor Chart</title>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/d3.v7.min.js"></script>
    <script src="<?php echo SWB; ?>plugins/slims-graph/pages/html2canvas.min.js"></script>
    <style>

        .container {
            max-width: 1100px;
            margin: auto;
        }
        .controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1rem;
        }
        .controls input, .controls button {
            padding: 0.4rem 0.7rem;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .controls button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        .controls button:hover {
            background: #0056b3;
        }
        .description {
            margin-top: 2rem;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        svg {
            width: 100%;
            height: 600px;
        }
    </style>
</head>
<body>
<!-- 
Plugin Name: SLiMS Graph Network Analysis
Plugin URI: https://github.com/erwansetyobudi/slims-graph
Description: Plugin untuk menampilkan peta jaringan keterkaitan di SLiMS
Version: 1.0.0
Author: Erwan Setyo Budi
Author URI: https://github.com/erwansetyobudi 
-->

<div class="container">
<?php include('graphbar.php'); ?>
    <form class="controls" onsubmit="updateLimit(event)">
    <label for="year">Tahun:</label>
    <select class="form-control" id="year" name="year">
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear - 5; $y <= $currentYear; $y++) {
            echo '<option value="' . $y . '"' . ($y == $year ? ' selected' : '') . '>' . $y . '</option>';
        }
        ?>
    </select>

    <label for="limit">Limit Data:</label>
    <input class="form-control" type="number" id="limit" name="limit" min="1" value="<?php echo $limit; ?>">

    <button class="btn btn-primary" type="submit">Terapkan</button>
    <button class="btn btn-primary" type="button" onclick="saveAsImage()">💾 Simpan JPG</button>
    <button class="btn btn-primary" type="button" onclick="shareUrl()">🔗 Salin Link</button>
    </form>


    <svg id="chart"></svg>

    <div class="description">
        <p><strong>Visualisasi ini</strong> menampilkan jumlah kunjungan perpustakaan berdasarkan jenis kelamin dalam satu tahun. Data ditampilkan dalam bentuk grafik horizontal dengan gaya mirip <em>Icelandic Population Chart</em>.</p>
        <ul>
            <li>🔵 Laki-laki ditampilkan ke kiri (negatif)</li>
            <li>🔴 Perempuan ke kanan (positif)</li>
            <li>Sumbu vertikal (Y) menunjukkan bulan</li>
            <li>Sumbu horizontal (X) menunjukkan jumlah kunjungan</li>
        </ul>
        <p>Grafik ini membantu memahami dinamika kunjungan antara gender sepanjang tahun.</p>
    </div>
</div>

<script>
const data = <?php echo json_encode($chartData); ?>;

const svg = d3.select("#chart");
const margin = {top: 20, right: 50, bottom: 30, left: 100};
const width = +svg.node().getBoundingClientRect().width - margin.left - margin.right;
const height = +svg.node().getBoundingClientRect().height - margin.top - margin.bottom;

const g = svg.append("g").attr("transform", `translate(${margin.left},${margin.top})`);

const y = d3.scaleBand()
    .domain(data.map(d => d.month))
    .range([0, height])
    .padding(0.2);

const x = d3.scaleLinear()
    .domain([
        -d3.max(data, d => d.male),
         d3.max(data, d => d.female)
    ])
    .nice()
    .range([0, width]);

// Sumbu
g.append("g")
    .attr("transform", `translate(0,0)`)
    .call(d3.axisLeft(y));

g.append("g")
    .attr("transform", `translate(0,${height})`)
    .call(d3.axisBottom(x).ticks(5).tickFormat(Math.abs));

// Bar Laki-laki ke kiri
g.selectAll(".male")
    .data(data)
    .enter().append("rect")
    .attr("x", d => x(-d.male))
    .attr("y", d => y(d.month))
    .attr("width", d => x(0) - x(-d.male))
    .attr("height", y.bandwidth())
    .attr("fill", "#1f77b4");

// Bar Perempuan ke kanan
g.selectAll(".female")
    .data(data)
    .enter().append("rect")
    .attr("x", x(0))
    .attr("y", d => y(d.month))
    .attr("width", d => x(d.female) - x(0))
    .attr("height", y.bandwidth())
    .attr("fill", "#e377c2");
</script>

<script>
function updateLimit(event) {
    event.preventDefault();
    const limit = document.getElementById("limit").value;
    const year = document.getElementById("year").value;
    const baseUrl = window.location.href.split('?')[0];
    window.location.href = `${baseUrl}?p=gender_visitor_chart&year=${year}&limit=${limit}`;
}


function saveAsImage() {
    html2canvas(document.querySelector('.container')).then(canvas => {
        let link = document.createElement("a");
        link.download = "gender-visitor-chart.jpg";
        link.href = canvas.toDataURL("image/jpeg", 1.0);
        link.click();
    });
}

function shareUrl() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert("Link berhasil disalin!");
    });
}
</script>
</body>
</html>
