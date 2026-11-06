<?php

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];

function getInput()
{
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        response(400, "Format JSON tidak valid");
    }

    return $input;
}

// Validasi data pengeluaran
function validasiPengeluaran($data)
{
    $nama = trim($data['nama_pengeluaran'] ?? '');
    $kategori = trim($data['kategori'] ?? '');
    $jumlah = $data['jumlah'] ?? '';
    $tanggal = $data['tanggal_pengeluaran'] ?? '';
    $keterangan = trim($data['keterangan'] ?? '');

    if (
        $nama === '' ||
        $kategori === '' ||
        $jumlah === '' ||
        $tanggal === ''
    ) {
        response(400, "Semua field wajib diisi");
    }

    if (!is_numeric($jumlah) || $jumlah <= 0) {
        response(400, "Jumlah pengeluaran harus lebih dari 0");
    }

    $date = DateTime::createFromFormat('!Y-m-d', $tanggal);

    if (!$date || $date->format('Y-m-d') !== $tanggal) {
        response(400, "Format tanggal harus YYYY-MM-DD");
    }

    return [
        $nama,
        $kategori,
        $jumlah,
        $tanggal,
        $keterangan
    ];
}

try {

    switch ($method) {

        case 'GET':

            if (isset($_GET['id'])) {

                $id = (int) $_GET['id'];

                if ($id <= 0) {
                    response(400, "ID tidak valid");
                }

                $stmt = $koneksi->prepare(
                    "SELECT * FROM pengeluaran
                    WHERE id_pengeluaran = ?"
                );

                $stmt->bind_param("i", $id);
                $stmt->execute();

                $result = $stmt->get_result();
                $data = $result->fetch_assoc();

                if (!$data) {
                    response(404, "Data pengeluaran tidak ditemukan");
                }

                response(200, "Data berhasil ditampilkan", $data);
            }

            // Pencarian dan pagination
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = (int) ($_GET['limit'] ?? 10);

            if ($limit < 1 || $limit > 100) {
                response(400, "Limit harus antara 1 sampai 100");
            }

            $offset = ($page - 1) * $limit;
            $keyword = "%" . $search . "%";

            $stmt = $koneksi->prepare(
                "SELECT * FROM pengeluaran
                WHERE nama_pengeluaran LIKE ?
                OR kategori LIKE ?
                ORDER BY tanggal_pengeluaran DESC
                LIMIT ? OFFSET ?"
            );

            $stmt->bind_param(
                "ssii",
                $keyword,
                $keyword,
                $limit,
                $offset
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $data = [];

            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            response(200, "Data pengeluaran berhasil ditampilkan", $data);


        case 'POST':

            $input = getInput();
            $data = validasiPengeluaran($input);

            [$nama, $kategori, $jumlah, $tanggal, $keterangan] = $data;

            // Cek data duplikat
            $cek = $koneksi->prepare(
                "SELECT id_pengeluaran FROM pengeluaran
                WHERE nama_pengeluaran = ?
                AND kategori = ?
                AND jumlah = ?
                AND tanggal_pengeluaran = ?"
            );

            $cek->bind_param(
                "ssds",
                $nama,
                $kategori,
                $jumlah,
                $tanggal
            );

            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                response(409, "Data pengeluaran sudah tersedia");
            }

            $stmt = $koneksi->prepare(
                "INSERT INTO pengeluaran
                (nama_pengeluaran, kategori, jumlah,
                tanggal_pengeluaran, keterangan)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssdss",
                $nama,
                $kategori,
                $jumlah,
                $tanggal,
                $keterangan
            );

            $stmt->execute();

            response(201, "Pengeluaran berhasil ditambahkan", [
                "id" => $koneksi->insert_id
            ]);

        case 'PUT':

            if (!isset($_GET['id'])) {
                response(400, "ID wajib diisi");
            }

            $id = (int) $_GET['id'];

            if ($id <= 0) {
                response(400, "ID tidak valid");
            }

            // Cek ID
            $cek = $koneksi->prepare(
                "SELECT id_pengeluaran FROM pengeluaran
                WHERE id_pengeluaran = ?"
            );

            $cek->bind_param("i", $id);
            $cek->execute();

            if ($cek->get_result()->num_rows === 0) {
                response(404, "Data pengeluaran tidak ditemukan");
            }

            $input = getInput();
            $data = validasiPengeluaran($input);

            [$nama, $kategori, $jumlah, $tanggal, $keterangan] = $data;

            // Cek duplikat selain ID yang sedang diedit
            $cek = $koneksi->prepare(
                "SELECT id_pengeluaran FROM pengeluaran
                WHERE nama_pengeluaran = ?
                AND kategori = ?
                AND jumlah = ?
                AND tanggal_pengeluaran = ?
                AND id_pengeluaran != ?"
            );

            $cek->bind_param(
                "ssdsi",
                $nama,
                $kategori,
                $jumlah,
                $tanggal,
                $id
            );

            $cek->execute();

            if ($cek->get_result()->num_rows > 0) {
                response(409, "Data pengeluaran sudah tersedia");
            }

            $stmt = $koneksi->prepare(
                "UPDATE pengeluaran SET
                nama_pengeluaran = ?,
                kategori = ?,
                jumlah = ?,
                tanggal_pengeluaran = ?,
                keterangan = ?
                WHERE id_pengeluaran = ?"
            );

            $stmt->bind_param(
                "ssdssi",
                $nama,
                $kategori,
                $jumlah,
                $tanggal,
                $keterangan,
                $id
            );

            $stmt->execute();

            response(200, "Data pengeluaran berhasil diperbarui");

        case 'DELETE':

            if (!isset($_GET['id'])) {
                response(400, "ID wajib diisi");
            }

            $id = (int) $_GET['id'];

            if ($id <= 0) {
                response(400, "ID tidak valid");
            }

            $stmt = $koneksi->prepare(
                "DELETE FROM pengeluaran
                WHERE id_pengeluaran = ?"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            if ($stmt->affected_rows === 0) {
                response(404, "Data pengeluaran tidak ditemukan");
            }

            response(200, "Data pengeluaran berhasil dihapus");


        default:

            header("Allow: GET, POST, PUT, DELETE");
            response(405, "Method tidak diizinkan");
    }
}catch (Throwable $e) {
    error_log("Error Pengeluaran: " . $e->getMessage());

    response(500, "Terjadi kesalahan pada server", [
        "error" => $e->getMessage()
    ]);
}
