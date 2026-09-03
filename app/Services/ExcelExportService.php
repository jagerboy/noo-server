<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Service untuk memproses ekspor berkas Excel (.xlsx) untuk NOO Verification / EDP Principal.
 * Mengikuti 100% persis spesifikasi TEMPLATE_NOO_1.xlsx:
 * - Sheet 1 (Template):
 *   - A2: 'Template Cust UnMapping Untuk Eskalink' (16pt Bold)
 *   - A4: 'Nama Distributor : [branchName]' (11pt Bold, text-wrap false agar melimpah keluar cell)
 *   - Column A width = 6 (agar NO tidak terlalu lebar)
 *   - Row 6 Header Banners (C6:F6 'Di isi Oleh Admin Dist', G6:H6 'Di isi oleh SPV Area', J6 'Di Isi EDP', K6:U6 'Di isi oleh SPV Area', V6:W6 'Di isi oleh SPV Area')
 *   - Row 7 Sub-headers dengan warna #D9EAD3 dan teks tebal
 *   - Row 8+ Data rows dengan border tipis dan auto-width kolom
 * - Sheet 2 (Ready to Inject): 35 Kolom ERP standar, BLOK NO = 01 (tanpa '), KODE TERM OF PAYMENT = 014
 */
class ExcelExportService
{
    /**
     * Membuat file Excel (.xlsx) untuk toko yang disetujui/ditolak.
     */
    public function generateExcel(array $submissions, string $type = 'APPROVED', string $branchName = ''): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        // Font default Arial / Calibri 10pt
        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Row 2: Title Template (Font size dibesarkan)
        $sheet->setCellValue('A2', 'Template Cust UnMapping Untuk Eskalink');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(22);

        // Row 4: Header Nama Distributor (Overflow / spill melimpah keluar dari sel A4)
        $sheet->setCellValue('A4', "Nama Distributor : {$branchName}");
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A4')->getAlignment()->setWrapText(false);

        // Row 6 & Row 7: Header Banners & Sub-headers
        // A6:F6 merged "Di isi Oleh Admin Dist"
        $sheet->mergeCells('A6:F6');
        $sheet->setCellValue('A6', 'Di isi Oleh Admin Dist');
        $sheet->getStyle('A6:F6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('A6:F6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A6:F6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // G6:I6 merged "Di isi oleh SPV Area"
        $sheet->mergeCells('G6:I6');
        $sheet->setCellValue('G6', 'Di isi oleh SPV Area');
        $sheet->getStyle('G6:I6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('G6:I6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('G6:I6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // J6: "Di Isi EDP"
        $sheet->setCellValue('J6', 'Di Isi EDP');
        $sheet->getStyle('J6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('J6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('J6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // K6:U6 merged "Di isi oleh SPV Area"
        $sheet->mergeCells('K6:U6');
        $sheet->setCellValue('K6', 'Di isi oleh SPV Area');
        $sheet->getStyle('K6:U6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('K6:U6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('K6:U6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // V6:V7 merged "LA"
        $sheet->mergeCells('V6:V7');
        $sheet->setCellValue('V6', 'LA');
        $sheet->getStyle('V6:V7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('V6:V7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('V6:V7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // W6:W7 merged "LG"
        $sheet->mergeCells('W6:W7');
        $sheet->setCellValue('W6', 'LG');
        $sheet->getStyle('W6:W7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('W6:W7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('W6:W7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Row 7 Sub-headers for A7:U7
        $headersRow7 = [
            'A7' => 'NO',
            'B7' => 'Tanggal NOO',
            'C7' => 'Code Cust UnMapping',
            'D7' => 'Nama Cust UnMapping',
            'E7' => 'Alamat Cust UnMapping',
            'F7' => 'Type Outlet UnMapping',
            'G7' => 'Approval SPV Area',
            'H7' => 'Kode SE (Eskalink)',
            'I7' => 'NORUTE',
            'J7' => 'CUSTNO',
            'K7' => 'H1', 'L7' => 'H2', 'M7' => 'H3', 'N7' => 'H4', 'O7' => 'H5', 'P7' => 'H6', 'Q7' => 'H7',
            'R7' => 'M1', 'S7' => 'M2', 'T7' => 'M3', 'U7' => 'M4'
        ];

        foreach ($headersRow7 as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle('A7:W7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A7:W7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('A7:W7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Border Tipis untuk Header (Row 6 & Row 7)
        $sheet->getStyle('A6:W7')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        $startRow = 8;
        foreach ($submissions as $index => $row) {
            $currentRow = $startRow + $index;

            $subDate = $row['submitted_at'] ?? $row['created_at'] ?? '';
            if (!empty($subDate)) {
                $subDate = date('d/m/Y', strtotime((string)$subDate));
            }

            $spvApproval = strtoupper((string)($row['approval_spv_area'] ?? ''));
            if ($spvApproval === 'YES' || $spvApproval === 'Y' || $spvApproval === 'APPROVED') {
                $spvApprovalText = 'YES';
            } else {
                $spvApprovalText = $spvApproval ?: 'YES';
            }

            $h1 = strtoupper((string)($row['h1'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h2 = strtoupper((string)($row['h2'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h3 = strtoupper((string)($row['h3'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h4 = strtoupper((string)($row['h4'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h5 = strtoupper((string)($row['h5'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h6 = strtoupper((string)($row['h6'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h7 = strtoupper((string)($row['h7'] ?? '')) === 'Y' ? 'Y' : 'T';

            $m1 = strtoupper((string)($row['m1'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m2 = strtoupper((string)($row['m2'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m3 = strtoupper((string)($row['m3'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m4 = strtoupper((string)($row['m4'] ?? '')) === 'Y' ? 'Y' : 'T';

            $rowData = [
                $index + 1,
                $subDate,
                $row['custcode_distributor'] ?? '',
                $row['nama_noo'] ?? '',
                $row['alamat_noo'] ?? '',
                $row['type_outlet_code'] ?? '',
                $spvApprovalText,
                $row['salesman_code'] ?? '',
                1,
                $row['code_noo_principal'] ?? '',
                $h1, $h2, $h3, $h4, $h5, $h6, $h7,
                $m1, $m2, $m3, $m4,
                $row['la'] ?? '', $row['lg'] ?? ''
            ];

            $sheet->fromArray($rowData, null, "A{$currentRow}");

            // Center NO
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Format Plain Text untuk Kode Customer
            $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('@');

            // Highlighting Rute H1-H7 & M1-M4 (Kuning Muda #FFF9C4 jika Y, Grey #F3F4F6 jika T)
            for ($colIdx = 11; $colIdx <= 21; $colIdx++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $val = strtoupper(trim((string) ($rowData[$colIdx - 1] ?? '')));

                $sheet->getStyle("{$colLetter}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($val === 'Y') {
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFont()
                        ->setColor(new Color('1B5E20'))
                        ->setBold(true);
                } elseif ($val === 'T') {
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F3F4F6');
                }
            }

            // Border tipis untuk seluruh sel data
            $sheet->getStyle("A{$currentRow}:W{$currentRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        }

        // Set khusus Column A (NO) agar tidak melebar karena teks A2 / A4
        $sheet->getColumnDimension('A')->setAutoSize(false);
        $sheet->getColumnDimension('A')->setWidth(6);

        // Auto-fit column widths untuk B s/d W
        foreach (range('B', 'W') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Tambahkan Sheet "Ready to Inject" jika jenis export APPROVED
        if ($type === 'APPROVED') {
            $this->appendReadyToInjectSheet($spreadsheet, $submissions);
        }

        // Ensure Sheet 1 ("Template") is the active sheet opened first in MS Excel
        $spreadsheet->setActiveSheetIndex(0);

        // Output file
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Menyusun sheet "Ready to Inject" sesuai struktur 01_edp_portal.gs & TEMPLATE_NOO_1.xlsx.
     */
    protected function appendReadyToInjectSheet(Spreadsheet $spreadsheet, array $submissions): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ready to Inject');

        $readyHeaders = [
            'BLOK NO', 'BLOK ID', 'KODE PELANGGAN', 'NOMOR BARKODE PELANGGAN', 'NAMA PELANGGAN',
            'ALAMAT PELANGGAN 1', 'ALAMAT PELANGGAN 2', 'KOTA PELANGGAN', 'PEMILIK / KONTAK PERSON PELANGGAN',
            'TELEPON PELANGGAN', 'NO FAX PELANGGAN', 'KODE TERM OF PAYMENT', 'VALUE CREDIT LIMIT', 'FLAG KREDIT LIMIT',
            'KODE GROUP DISCOUNT', 'KODE TYPE OUTLET', 'KODE GROUP OUTLET', 'KODE GROUP HARGA',
            'DEFAULT TYPE PEMBAYARAN', 'FLAG OUTLET REGISTER', 'RATA-RATA PENJUALAN PELANGGAN', 'NILAI TERAKHIR PELANGGAN MELAKUKAN TRANSAKSI',
            'TANGGAL TERAKHIR PELANGGAN MELAKUKAN TRANSAKSI', 'KODE LOKASI', 'KODE DISTRIC', 'KODE BEAT', 'KODE SUB BEAT',
            'KODE CLASSIFIKASI PELANGGAN', 'KODE CHANNEL', 'KODE PASAR', 'KODE DISTRIBUTOR/CABANG',
            'LA', 'LG', 'Column1', 'Rumus'
        ];

        $sheet->fromArray($readyHeaders, null, 'A1');
        $sheet->getStyle('A1:AI1')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A1:AI1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');

        // Kolom R Header (KODE GROUP HARGA) diberi warna Kuning Muda #FFF2CC & teks merah tebal
        $sheet->getStyle('R1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
        $sheet->getStyle('R1')->getFont()->setColor(new Color('CC0000'))->setBold(true);

        $sheet->getStyle('A1:AI1')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        $startRow = 2;
        foreach ($submissions as $index => $row) {
            $rNum = $startRow + $index;
            $typeOutlet = (string) ($row['type_outlet_code'] ?? '');
            $groupOutlet = substr($typeOutlet, 0, 2);
            $kotaPelanggan = !empty($row['kab_kota_noo']) ? $row['kab_kota_noo'] : 'INDONESIA';

            // BLOK NO diisi "01", KODE TERM OF PAYMENT "014"
            $readyData = [
                "01", "MCUST", $row['code_noo_principal'] ?? '', '', $row['nama_noo'] ?? '',
                $row['alamat_noo'] ?? '', '', $kotaPelanggan, '',
                '', '', "014", '', 'Y',
                '', $typeOutlet, $groupOutlet, '',
                'K', 'C', '', '',
                '', '', '', '', '',
                '', $typeOutlet, '', $row['branch_id'] ?? '',
                $row['la'] ?? '', $row['lg'] ?? '', ''
            ];

            $sheet->fromArray($readyData, null, "A{$rNum}");

            // Explicit String untuk BLOK NO ("01") & KODE TERM OF PAYMENT ("014")
            $sheet->getStyle("A{$rNum}")->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle("C{$rNum}")->getNumberFormat()->setFormatCode('@');
            $sheet->getStyle("L{$rNum}")->getNumberFormat()->setFormatCode('@');

            $sheet->setCellValueExplicit("A{$rNum}", "01", \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("L{$rNum}", "014", \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // Highlight Kolom R (KODE GROUP HARGA)
            $sheet->getStyle("R{$rNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');

            // Formula Pipe-Separated pada Kolom AI
            $formula = "=A{$rNum}&\"|\"&B{$rNum}&\"|\"&C{$rNum}&\"|\"&D{$rNum}&\"|\"&E{$rNum}" .
                       "&\"|\"&F{$rNum}&\"|\"&G{$rNum}&\"|\"&H{$rNum}&\"|\"&I{$rNum}" .
                       "&\"|\"&J{$rNum}&\"|\"&K{$rNum}&\"|\"&L{$rNum}&\"|\"&M{$rNum}" .
                       "&\"|\"&N{$rNum}&\"|\"&O{$rNum}&\"|\"&P{$rNum}&\"|\"&Q{$rNum}" .
                       "&\"|\"&R{$rNum}&\"|\"&S{$rNum}&\"|\"&T{$rNum}&\"|\"&U{$rNum}" .
                       "&\"|\"&V{$rNum}&\"|\"&W{$rNum}&\"|\"&X{$rNum}&\"|\"&Y{$rNum}" .
                       "&\"|\"&Z{$rNum}&\"|\"&AA{$rNum}&\"|\"&AB{$rNum}&\"|\"&AC{$rNum}" .
                       "&\"|\"&AD{$rNum}&\"|\"&AE{$rNum}&\"|\"&AF{$rNum}&\"|\"&AG{$rNum}";

            $sheet->setCellValue("AI{$rNum}", $formula);

            // Border tipis untuk sel data
            $sheet->getStyle("A{$rNum}:AI{$rNum}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        }

        // Auto-fit column widths untuk Sheet 2
        foreach (range('A', 'Z') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI'] as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
    }

    /**
     * Membuat file Excel (.xlsx) untuk toko yang ditolak (Rejected EDP).
     * Sesuai spesifikasi Template_reject_NOO.xlsx
     */
    public function generateRejectedExcel(array $submissions, string $branchName = ''): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Reject');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Row 2: Title (Font size dibesarkan)
        $sheet->setCellValue('A2', 'Template Reject NOO');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(22);

        // Row 6 Section Banners: A6:F6 Admin Dist, G6:M6 SPV Area & EDP, N6:X6 SPV Area, Y6:Y7 LA, Z6:Z7 LG
        $sheet->mergeCells('A6:F6');
        $sheet->setCellValue('A6', 'Di isi Oleh Admin Dist');
        $sheet->getStyle('A6:F6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('A6:F6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A6:F6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells('G6:M6');
        $sheet->setCellValue('G6', 'Di isi oleh SPV Area & EDP');
        $sheet->getStyle('G6:M6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('G6:M6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('G6:M6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells('N6:X6');
        $sheet->setCellValue('N6', 'Di isi oleh SPV Area');
        $sheet->getStyle('N6:X6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('N6:X6')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('N6:X6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells('Y6:Y7');
        $sheet->setCellValue('Y6', 'LA');
        $sheet->getStyle('Y6:Y7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('Y6:Y7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('Y6:Y7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->mergeCells('Z6:Z7');
        $sheet->setCellValue('Z6', 'LG');
        $sheet->getStyle('Z6:Z7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('Z6:Z7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('Z6:Z7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Row 7 Sub-headers
        $headersRow7 = [
            'A7' => 'NO',
            'B7' => 'Tanggal NOO',
            'C7' => 'Code Cust UnMapping',
            'D7' => 'Nama Cust UnMapping',
            'E7' => 'Alamat Cust UnMapping',
            'F7' => 'Type Outlet UnMapping',
            'G7' => 'Branch ID',
            'H7' => 'Branch Name',
            'I7' => 'Approval SPV Area',
            'J7' => 'Kode SE (Eskalink)',
            'K7' => 'NORUTE',
            'L7' => 'CUSTNO',
            'M7' => 'REASON',
            'N7' => 'H1', 'O7' => 'H2', 'P7' => 'H3', 'Q7' => 'H4', 'R7' => 'H5', 'S7' => 'H6', 'T7' => 'H7',
            'U7' => 'M1', 'V7' => 'M2', 'W7' => 'M3', 'X7' => 'M4'
        ];

        foreach ($headersRow7 as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $sheet->getStyle('A7:Z7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A7:Z7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
        $sheet->getStyle('A7:Z7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A6:Z7')->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');

        $startRow = 8;
        foreach ($submissions as $index => $row) {
            $currentRow = $startRow + $index;

            $subDate = $row['submitted_at'] ?? $row['created_at'] ?? '';
            if (!empty($subDate)) {
                $subDate = date('d/m/Y', strtotime((string)$subDate));
            }

            $spvApproval = strtoupper((string)($row['approval_spv_area'] ?? ''));
            if ($spvApproval === 'YES' || $spvApproval === 'Y' || $spvApproval === 'APPROVED') {
                $spvApprovalText = 'YES';
            } else {
                $spvApprovalText = $spvApproval ?: 'YES';
            }

            $h1 = strtoupper((string)($row['h1'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h2 = strtoupper((string)($row['h2'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h3 = strtoupper((string)($row['h3'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h4 = strtoupper((string)($row['h4'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h5 = strtoupper((string)($row['h5'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h6 = strtoupper((string)($row['h6'] ?? '')) === 'Y' ? 'Y' : 'T';
            $h7 = strtoupper((string)($row['h7'] ?? '')) === 'Y' ? 'Y' : 'T';

            $m1 = strtoupper((string)($row['m1'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m2 = strtoupper((string)($row['m2'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m3 = strtoupper((string)($row['m3'] ?? '')) === 'Y' ? 'Y' : 'T';
            $m4 = strtoupper((string)($row['m4'] ?? '')) === 'Y' ? 'Y' : 'T';

            $reasonText = $row['edp_notes'] ?? $row['reject_reason'] ?? '-';

            $rowData = [
                $index + 1,
                $subDate,
                $row['custcode_distributor'] ?? '',
                $row['nama_noo'] ?? '',
                $row['alamat_noo'] ?? '',
                $row['type_outlet_code'] ?? '',
                $row['branch_id'] ?? '',
                $row['branch_name'] ?? '',
                $spvApprovalText,
                $row['salesman_code'] ?? '',
                1,
                '-',
                $reasonText,
                $h1, $h2, $h3, $h4, $h5, $h6, $h7,
                $m1, $m2, $m3, $m4,
                $row['la'] ?? '', $row['lg'] ?? ''
            ];

            $sheet->fromArray($rowData, null, "A{$currentRow}");

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('@');

            // Highlighting Rute H1-H7 & M1-M4
            for ($colIdx = 14; $colIdx <= 24; $colIdx++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
                $val = strtoupper(trim((string) ($rowData[$colIdx - 1] ?? '')));

                $sheet->getStyle("{$colLetter}{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                if ($val === 'Y') {
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('FFF9C4');
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFont()
                        ->setColor(new Color('1B5E20'))
                        ->setBold(true);
                } elseif ($val === 'T') {
                    $sheet->getStyle("{$colLetter}{$currentRow}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F3F4F6');
                }
            }

            // Border tipis untuk seluruh sel data
            $sheet->getStyle("A{$currentRow}:Z{$currentRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('000000');
        }

        $sheet->getColumnDimension('A')->setAutoSize(false);
        $sheet->getColumnDimension('A')->setWidth(6);

        foreach (range('B', 'Z') as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * Membuat file Excel (.xlsx) untuk riwayat Audit Activity & System Logs.
     */
    public function generateActivityLogsExcel(array $logs, string $filterRole = 'ALL', string $search = ''): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Logs');

        $spreadsheet->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // Row 2: Title Header
        $sheet->setCellValue('A2', 'AUDIT ACTIVITY & SYSTEM LOGS - PORTAL NOO+');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16)->setColor(new Color('111827'));

        // Row 3: Meta Information
        $generatedAt = date('d/m/Y H:i:s');
        $infoText = "Waktu Unduh: {$generatedAt} | Filter Role: {$filterRole}" . ($search ? " | Pencarian: {$search}" : "");
        $sheet->setCellValue('A3', $infoText);
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(10)->setColor(new Color('4B5563'));

        // Row 5: Table Header
        $headers = [
            'A5' => 'NO',
            'B5' => 'WAKTU / TANGGAL',
            'C5' => 'PENGGUNA (USERNAME)',
            'D5' => 'PERAN (ROLE)',
            'E5' => 'AKSI (ACTION)',
            'F5' => 'MODUL',
            'G5' => 'DESKRIPSI AUDIT',
            'H5' => 'IP ADDRESS',
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        // Header Styling (#107C41 / Emerald-700 with White Text)
        $sheet->getStyle('A5:H5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('059669');
        $sheet->getStyle('A5:H5')->getFont()->setBold(true)->setSize(10)->setColor(new Color('FFFFFF'));
        $sheet->getStyle('A5:H5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(5)->setRowHeight(26);

        $startRow = 6;
        $currentRow = $startRow;

        $roleLabels = [
            'SUPERADMIN' => 'Superadmin',
            'ADMIN_PRINCIPAL' => 'Admin Principal',
            'EDP_REGION' => 'EDP Region',
            'SPV_AREA' => 'SPV Area',
            'ADMIN_DISTRIBUTOR' => 'Admin Distributor',
        ];

        foreach ($logs as $index => $log) {
            $log = (array) $log;
            $createdTime = !empty($log['created_at']) ? date('d/m/Y H:i:s', strtotime((string)$log['created_at'])) : '-';
            $userRole = (string)($log['user_role'] ?? '-');
            $roleDisplay = $roleLabels[$userRole] ?? str_replace('_', ' ', $userRole);

            $rowData = [
                $index + 1,
                $createdTime,
                $log['username'] ?? '-',
                $roleDisplay,
                $log['action'] ?? '-',
                $log['module'] ?? '-',
                $log['description'] ?? '-',
                $log['ip_address'] ?? '-',
            ];

            $sheet->fromArray($rowData, null, "A{$currentRow}");

            // Row height & zebra striping
            $sheet->getRowDimension($currentRow)->setRowHeight(20);
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$currentRow}:H{$currentRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
            }

            // Text Alignments
            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("E{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

            // Borders
            $sheet->getStyle("A{$currentRow}:H{$currentRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');

            $currentRow++;
        }

        // Set column widths
        $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
        $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(20);
        $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(22);
        $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(18);
        $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(20);
        $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(18);
        $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(48);
        $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(16);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}
