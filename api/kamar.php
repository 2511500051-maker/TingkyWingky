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
        sendResponse(false, "ID kamar tidak valid", null, 400);
    }

    $query = "
        SELECT 
            k.*,
            p.nama_penyewa,
            p.no_telepon
        FROM kamar k
        LEFT JOIN kontrak c ON k.id_kamar = c.id_kamar
            AND c.id_kontrak = (
                SELECT MAX(c2.id_kontrak)
                FROM kontrak c2
                WHERE c2.id_kamar = k.id_kamar
            )
        LEFT JOIN penyewa p ON c.id_penyewa = p.id_penyewa
        WHERE k.id_kamar = ?
    ";

    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);

    if (!$data) {
        sendResponse(false, "Data kamar tidak ditemukan", null, 404);
    }

    sendResponse(true, "Data kamar berhasil ditemukan", $data);

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

// Menghitung jumlah data
$countQuery = "
    SELECT COUNT(*) AS total
    FROM kamar
    WHERE nomor_kamar LIKE ?
    OR tipe_kamar LIKE ?
    OR status_kamar LIKE ?
";

$keyword = "%".$search."%";

$countStmt = mysqli_prepare($koneksi, $countQuery);
mysqli_stmt_bind_param(
    $countStmt,
    "sss",
    $keyword,
    $keyword,
    $keyword
);

mysqli_stmt_execute($countStmt);

$countResult = mysqli_stmt_get_result($countStmt);
$totalData = mysqli_fetch_assoc($countResult)['total'];

// Mengambil data kamar
$query = "
    SELECT 
        k.*,
        p.nama_penyewa,
        p.no_telepon
    FROM kamar k
    LEFT JOIN kontrak c ON k.id_kamar = c.id_kamar
        AND c.id_kontrak = (
            SELECT MAX(c2.id_kontrak)
            FROM kontrak c2
            WHERE c2.id_kamar = k.id_kamar
        )
    LEFT JOIN penyewa p ON c.id_penyewa = p.id_penyewa
    WHERE k.nomor_kamar LIKE ?
    OR k.tipe_kamar LIKE ?
    OR k.status_kamar LIKE ?
    ORDER BY k.id_kamar DESC
    LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($koneksi, $query);

mysqli_stmt_bind_param(
    $stmt,
    "sssii",
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

sendResponse(true, "Data kamar berhasil ditampilkan", [
    "total_data" => (int) $totalData,
    "halaman" => $page,
    "batas_data" => $limit,
    "data_kamar" => $data
]);

?>