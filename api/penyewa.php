<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method != 'GET') {
    sendResponse(false, "Method tidak diizinkan", null, 405);
}

// GET berdasarkan ID
if (isset($_GET['id'])) {

    $id = (int) $_GET['id'];

    if ($id <= 0) {
        sendResponse(false, "ID penyewa tidak valid", null, 400);
    }

    $query = "
        SELECT
            p.*,
            c.nomor_kontrak,
            c.tanggal_mulai,
            c.tanggal_berakhir,
            c.status_kontrak,
            k.nomor_kamar,
            k.tipe_kamar
        FROM penyewa p
        LEFT JOIN kontrak c ON p.id_penyewa = c.id_penyewa
            AND c.id_kontrak = (
                SELECT MAX(c2.id_kontrak)
                FROM kontrak c2
                WHERE c2.id_penyewa = p.id_penyewa
            )
        LEFT JOIN kamar k ON c.id_kamar = k.id_kamar
        WHERE p.id_penyewa = ?
    ";

    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    if (!$data) {
        sendResponse(false, "Data penyewa tidak ditemukan", null, 404);
    }

    sendResponse(true, "Data penyewa berhasil ditemukan", $data);
}

// Pencarian
$search = $_GET['search'] ?? '';

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = (int) ($_GET['limit'] ?? 10);

if ($limit < 1) {
    $limit = 10;
}

if ($limit > 100) {
    $limit = 100;
}

$offset = ($page - 1) * $limit;

$keyword = "%" . $search . "%";

// Menghitung jumlah data
$countQuery = "
    SELECT COUNT(*) AS total
    FROM penyewa
    WHERE nama_penyewa LIKE ?
    OR nik LIKE ?
    OR no_telepon LIKE ?
    OR email LIKE ?
";

$countStmt = mysqli_prepare($koneksi, $countQuery);

mysqli_stmt_bind_param(
    $countStmt,
    "ssss",
    $keyword,
    $keyword,
    $keyword,
    $keyword
);

mysqli_stmt_execute($countStmt);

$countResult = mysqli_stmt_get_result($countStmt);
$totalData = mysqli_fetch_assoc($countResult)['total'];

// Mengambil data penyewa
$query = "
    SELECT
        p.*,
        c.nomor_kontrak,
        c.status_kontrak,
        k.nomor_kamar,
        k.tipe_kamar
    FROM penyewa p
    LEFT JOIN kontrak c ON p.id_penyewa = c.id_penyewa
        AND c.id_kontrak = (
            SELECT MAX(c2.id_kontrak)
            FROM kontrak c2
            WHERE c2.id_penyewa = p.id_penyewa
        )
    LEFT JOIN kamar k ON c.id_kamar = k.id_kamar
    WHERE p.nama_penyewa LIKE ?
    OR p.nik LIKE ?
    OR p.no_telepon LIKE ?
    OR p.email LIKE ?
    ORDER BY p.id_penyewa DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($koneksi, $query);

mysqli_stmt_bind_param(
    $stmt,
    "ssssii",
    $keyword,
    $keyword,
    $keyword,
    $keyword,
    $limit,
    $offset
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

sendResponse(true, "Data penyewa berhasil ditampilkan", [
    "total_data" => (int) $totalData,
    "halaman" => $page,
    "batas_data" => $limit,
    "data_penyewa" => $data
]);

?>