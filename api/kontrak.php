<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];

if ($method != 'POST') {
    sendResponse(false, "Method tidak diizinkan", null, 405);
}

// Mengambil data JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    sendResponse(false, "Format JSON tidak valid", null, 400);
}

// Mengambil data dari JSON
$id_kamar = $input['id_kamar'] ?? '';
$id_penyewa = $input['id_penyewa'] ?? '';
$nomor_kontrak = trim($input['nomor_kontrak'] ?? '');
$tanggal_mulai = $input['tanggal_mulai'] ?? '';
$tanggal_berakhir = $input['tanggal_berakhir'] ?? '';
$harga_kontrak = $input['harga_kontrak'] ?? '';
$isi_kontrak = $input['isi_kontrak'] ?? '';
$status_kontrak = trim($input['status_kontrak'] ?? '');

// Validasi data wajib
if (
    $id_kamar === '' ||
    $id_penyewa === '' ||
    $nomor_kontrak === '' ||
    $tanggal_mulai === '' ||
    $tanggal_berakhir === '' ||
    $harga_kontrak === '' ||
    $status_kontrak === ''
) {
    sendResponse(false, "Semua data wajib harus diisi", null, 400);
}

// Validasi status kontrak
$status_valid = ['Aktif', 'Selesai', 'Dibatalkan'];

if (!in_array($status_kontrak, $status_valid, true)) {
    sendResponse(false, "Status kontrak tidak valid", null, 400);
}
// Validasi angka
if (
    !filter_var($id_kamar, FILTER_VALIDATE_INT) ||
    !filter_var($id_penyewa, FILTER_VALIDATE_INT) ||
    !is_numeric($harga_kontrak) ||
    $id_kamar <= 0 ||
    $id_penyewa <= 0 ||
    $harga_kontrak <= 0
) {
    sendResponse(false, "ID atau harga kontrak tidak valid", null, 400);
}

// Validasi tanggal
$tanggal1 = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
$tanggal2 = DateTime::createFromFormat('Y-m-d', $tanggal_berakhir);

if (
    !$tanggal1 ||
    !$tanggal2 ||
    $tanggal1->format('Y-m-d') !== $tanggal_mulai ||
    $tanggal2->format('Y-m-d') !== $tanggal_berakhir ||
    $tanggal1 >= $tanggal2
) {
    sendResponse(false, "Tanggal kontrak tidak valid", null, 400);
}

// Memeriksa kamar
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_kamar FROM kamar WHERE id_kamar = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id_kamar);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    sendResponse(false, "Kamar tidak ditemukan", null, 404);
}

// Memeriksa penyewa
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_penyewa FROM penyewa WHERE id_penyewa = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id_penyewa);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    sendResponse(false, "Penyewa tidak ditemukan", null, 404);
}

// Memeriksa nomor kontrak duplikat
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_kontrak FROM kontrak WHERE nomor_kontrak = ?"
);

mysqli_stmt_bind_param($stmt, "s", $nomor_kontrak);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    sendResponse(false, "Nomor kontrak sudah digunakan", null, 409);
}

// Menambahkan kontrak
$query = "
    INSERT INTO kontrak
    (
        id_kamar,
        id_penyewa,
        nomor_kontrak,
        tanggal_mulai,
        tanggal_berakhir,
        harga_kontrak,
        isi_kontrak,
        status_kontrak
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($koneksi, $query);

mysqli_stmt_bind_param(
    $stmt,
    "iisssdss",
    $id_kamar,
    $id_penyewa,
    $nomor_kontrak,
    $tanggal_mulai,
    $tanggal_berakhir,
    $harga_kontrak,
    $isi_kontrak,
    $status_kontrak
);

if (mysqli_stmt_execute($stmt)) {

    $data = [
        "id_kontrak" => mysqli_insert_id($koneksi),
        "nomor_kontrak" => $nomor_kontrak
    ];

    sendResponse(true, "Data kontrak berhasil ditambahkan", $data, 201);
} else {
    sendResponse(false, "Gagal menambahkan kontrak", null, 400);
}
