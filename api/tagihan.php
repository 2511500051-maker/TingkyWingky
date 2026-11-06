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

// Mengambil data
$id_kontrak = $input['id_kontrak'] ?? '';
$periode_bulan = trim($input['periode_bulan'] ?? '');
$tanggal_tagihan = $input['tanggal_tagihan'] ?? '';
$tanggal_jatuh_tempo = $input['tanggal_jatuh_tempo'] ?? '';
$jumlah_tagihan = $input['jumlah_tagihan'] ?? '';
$tanggal_bayar = $input['tanggal_bayar'] ?? null;
$metode_pembayaran = $input['metode_pembayaran'] ?? null;
$status_pembayaran = trim($input['status_pembayaran'] ?? '');

// Validasi data wajib
if (
     !isset($input['id_kontrak']) ||
    $periode_bulan === '' ||
    $tanggal_tagihan === '' ||
    $tanggal_jatuh_tempo === '' ||
    $jumlah_tagihan === '' ||
    $status_pembayaran === ''
) {
    sendResponse(false, "Semua data wajib harus diisi", null, 400);
}

// Validasi periode bulan
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode_bulan)) {
    sendResponse(false, "Format periode bulan tidak valid", null, 400);
}

// Validasi status pembayaran
$status_valid = ['Belum Bayar', 'Lunas', 'Terlambat'];

if (!in_array($status_pembayaran, $status_valid, true)) {
    sendResponse(false, "Status pembayaran tidak valid", null, 400);
}

// Validasi metode pembayaran
$metode_valid = ['Transfer', 'Tunai', 'QRIS'];

if (
    $metode_pembayaran !== null &&
    $metode_pembayaran !== '' &&
    !in_array($metode_pembayaran, $metode_valid, true)
) {
    sendResponse(false, "Metode pembayaran tidak valid", null, 400);
}

// Validasi angka
if (
    !filter_var($id_kontrak, FILTER_VALIDATE_INT) ||
    $id_kontrak <= 0 ||
    !is_numeric($jumlah_tagihan) ||
    $jumlah_tagihan <= 0
) {
    sendResponse(false, "ID kontrak atau jumlah tagihan tidak valid", null, 400);
}

// Fungsi validasi tanggal
function tanggalValid($tanggal)
{
    $date = DateTime::createFromFormat('Y-m-d', $tanggal);

    return $date && $date->format('Y-m-d') === $tanggal;
}

// Validasi tanggal tagihan
if (
    !tanggalValid($tanggal_tagihan) ||
    !tanggalValid($tanggal_jatuh_tempo)
) {
    sendResponse(false, "Format tanggal tidak valid", null, 400);
}

if ($tanggal_jatuh_tempo < $tanggal_tagihan) {
    sendResponse(false, "Tanggal jatuh tempo tidak boleh sebelum tanggal tagihan", null, 400);
}

// Validasi tanggal bayar jika diisi
if ($tanggal_bayar !== null && $tanggal_bayar !== '') {
    if (!tanggalValid($tanggal_bayar)) {
        sendResponse(false, "Tanggal pembayaran tidak valid", null, 400);
    }
} else {
    $tanggal_bayar = null;
}

// Validasi tanggal pembayaran jika status lunas
if ($status_pembayaran === 'Lunas' && empty($tanggal_bayar)) {
    sendResponse(false, "Tanggal pembayaran wajib diisi jika sudah lunas", null, 400);
}

// Memeriksa kontrak
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_kontrak FROM kontrak WHERE id_kontrak = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id_kontrak);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    sendResponse(false, "Kontrak tidak ditemukan", null, 404);
}

// Memeriksa tagihan duplikat
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT id_tagihan FROM tagihan
     WHERE id_kontrak = ? AND periode_bulan = ?"
);

mysqli_stmt_bind_param($stmt, "is", $id_kontrak, $periode_bulan);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    sendResponse(false, "Tagihan untuk periode tersebut sudah ada", null, 409);
}

// Menambahkan tagihan
$query = "
    INSERT INTO tagihan
    (
        id_kontrak,
        periode_bulan,
        tanggal_tagihan,
        tanggal_jatuh_tempo,
        jumlah_tagihan,
        tanggal_bayar,
        metode_pembayaran,
        status_pembayaran
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($koneksi, $query);

mysqli_stmt_bind_param(
    $stmt,
    "isssdsss",
    $id_kontrak,
    $periode_bulan,
    $tanggal_tagihan,
    $tanggal_jatuh_tempo,
    $jumlah_tagihan,
    $tanggal_bayar,
    $metode_pembayaran,
    $status_pembayaran
);

if (mysqli_stmt_execute($stmt)) {

    $data = [
        "id_tagihan" => mysqli_insert_id($koneksi),
        "id_kontrak" => $id_kontrak,
        "periode_bulan" => $periode_bulan
    ];

    sendResponse(true, "Tagihan berhasil ditambahkan", $data, 201);

} else {
    sendResponse(false, "Gagal menambahkan tagihan", null, 500);
}

?>