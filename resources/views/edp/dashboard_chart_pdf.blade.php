<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $chartTitle }} - Portal NOO+</title>
  <style>
    @page {
      size: A4 landscape;
      margin: 10mm;
    }
    * {
      box-sizing: border-box;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #0f172a;
      background-color: #ffffff;
      margin: 0;
      padding: 12px;
      font-size: 8pt;
      line-height: 1.35;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .action-bar {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 10px 16px;
      border-radius: 8px;
      margin-bottom: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn {
      padding: 6px 14px;
      border-radius: 6px;
      font-size: 8.5pt;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid transparent;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .btn-primary {
      background-color: #0f766e;
      color: #ffffff;
    }
    .btn-secondary {
      background-color: #f1f5f9;
      color: #334155;
      border-color: #cbd5e1;
    }
    .header-section {
      border-bottom: 2px solid #0f766e;
      padding-bottom: 8px;
      margin-bottom: 12px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .brand-title {
      font-size: 13pt;
      font-weight: 800;
      color: #0f172a;
      letter-spacing: -0.3px;
      text-transform: uppercase;
    }
    .chart-title {
      font-size: 10.5pt;
      font-weight: 700;
      color: #0f766e;
      margin-top: 2px;
    }
    .filter-grid {
      background-color: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 8px 12px;
      margin-bottom: 12px;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px 16px;
      font-size: 7.5pt;
    }
    .filter-item {
      display: flex;
      gap: 4px;
    }
    .filter-label {
      font-weight: 700;
      color: #475569;
      min-width: 90px;
    }
    .filter-value {
      color: #0f172a;
      font-weight: 600;
    }
    .section-heading {
      font-size: 8.5pt;
      font-weight: 700;
      color: #0f172a;
      text-transform: uppercase;
      margin: 12px 0 6px 0;
      letter-spacing: 0.3px;
    }
    /* FIT TO WIDTH TABLE DESIGN */
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-bottom: 14px;
      font-size: 7.5pt;
    }
    th, td {
      border: 1px solid #cbd5e1;
      padding: 4px 6px;
      word-wrap: break-word;
      overflow-wrap: break-word;
      vertical-align: middle;
    }
    th {
      background-color: #0f766e !important;
      color: #ffffff !important;
      font-weight: 700;
      text-align: center;
      font-size: 7.5pt;
      text-transform: uppercase;
    }
    .summary-th {
      background-color: #1e3a8a !important;
    }
    tr:nth-child(even) td {
      background-color: #f8fafc !important;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .font-bold { font-weight: 700; }
    .status-badge {
      display: inline-block;
      padding: 1.5px 5px;
      border-radius: 3px;
      font-size: 6.5pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }
    .status-approved { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .status-rejected { background-color: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3; }
    .status-pending { background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

    @media print {
      body {
        padding: 0;
      }
      .no-print {
        display: none !important;
      }
      table {
        page-break-inside: auto;
      }
      tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      thead {
        display: table-header-group;
      }
    }
  </style>
</head>
<body>

  <!-- BAR KONTROL CETAK (TIDAK MUNCUL SAAT CETAK / SAVE PDF) -->
  <div class="action-bar no-print">
    <div>
      <span class="font-bold" style="font-size: 9pt; color: #0f172a;">Pratinjau Dokumen PDF Resmi</span>
      <span style="font-size: 8pt; color: #64748b; margin-left: 8px;">Tampilan fit to width siap simpan / cetak</span>
    </div>
    <div style="display: flex; gap: 8px;">
      <button onclick="window.print()" class="btn btn-primary">
        <span>Cetak / Simpan PDF</span>
      </button>
      <button onclick="window.close()" class="btn btn-secondary">
        <span>Tutup</span>
      </button>
    </div>
  </div>

  <!-- HEADER DOKUMEN -->
  <div class="header-section">
    <div>
      <div class="brand-title">PORTAL PRINCIPAL NOO+ - LAPORAN GRAFIK EKSEKUTIF</div>
      <div class="chart-title">{{ strtoupper($chartTitle) }}</div>
    </div>
    <div style="text-align: right; font-size: 7.5pt; color: #64748b;">
      <div>Dokumen Resmi Sistem NOO+</div>
      <div class="font-mono">Waktu Ekspor: {{ $filterInfo['printed_at'] ?? date('d/m/Y H:i') }}</div>
    </div>
  </div>

  <!-- FILTER METADATA BOX -->
  <div class="filter-grid">
    <div class="filter-item">
      <span class="filter-label">Periode Tahun:</span>
      <span class="filter-value">{{ $filterInfo['year'] ?? 'Semua Tahun' }}</span>
    </div>
    <div class="filter-item">
      <span class="filter-label">Bulan Pengajuan:</span>
      <span class="filter-value">{{ $filterInfo['months'] ?? 'Semua Bulan' }}</span>
    </div>
    <div class="filter-item">
      <span class="filter-label">Region Area:</span>
      <span class="filter-value">{{ $filterInfo['region'] ?? 'Semua Region' }}</span>
    </div>
    <div class="filter-item">
      <span class="filter-label">Entity / Principal:</span>
      <span class="filter-value">{{ $filterInfo['entity'] ?? 'Semua Entity' }}</span>
    </div>
    <div class="filter-item">
      <span class="filter-label">Cabang Distributor:</span>
      <span class="filter-value">{{ $filterInfo['branch'] ?? 'Semua Cabang' }}</span>
    </div>
    <div class="filter-item">
      <span class="filter-label">Total Data:</span>
      <span class="filter-value font-mono">{{ count($details) }} Pengajuan NOO</span>
    </div>
  </div>

  <!-- VISUALISASI GRAFIK -->
  <div class="section-heading">Visualisasi Grafik</div>

  @if(!empty($chart_image))
    <div style="text-align: center; margin-bottom: 14px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px;">
      <img src="{{ $chart_image }}" style="max-height: 250px; max-width: 100%; object-fit: contain;" alt="Grafik" />
    </div>
  @else
    @if($chartType === 'comparison')
      @php
        $tot = $summaryRows[0]['count'] ?? 0;
        $app = $summaryRows[1]['count'] ?? 0;
        $rej = $summaryRows[2]['count'] ?? 0;
        $appDash = $tot > 0 ? min(100, ($app / $tot) * 100) : 0;
        $rejDash = $tot > 0 ? min(100, ($rej / $tot) * 100) : 0;
      @endphp
      <div style="display: flex; align-items: center; justify-content: space-around; gap: 24px; margin-bottom: 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px;">
        <!-- SVG Donut Chart Vector -->
        <div style="position: relative; width: 130px; height: 130px; flex-shrink: 0;">
          <svg viewBox="0 0 36 36" style="width: 100%; height: 100%; transform: rotate(-90deg);">
            <path stroke="#e2e8f0" stroke-width="3.8" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <path stroke="#10b981" stroke-dasharray="{{ $appDash }}, 100" stroke-width="4.5" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
            <path stroke="#f43f5e" stroke-dasharray="{{ $rejDash }}, 100" stroke-dashoffset="-{{ $appDash }}" stroke-width="4.5" stroke-linecap="round" fill="none" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
          </svg>
          <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
            <span style="font-size: 15pt; font-weight: 900; color: #0f172a; line-height: 1;">{{ number_format($tot) }}</span>
            <span style="font-size: 6pt; font-weight: 700; color: #64748b; text-transform: uppercase;">Total NOO</span>
          </div>
        </div>

        <!-- Metric Cards -->
        <div style="display: flex; gap: 12px; flex: 1; max-width: 650px;">
          <div style="flex: 1; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 8px 12px;">
            <div style="font-size: 6.5pt; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">1. Total Submit SE</div>
            <div style="font-size: 15pt; font-weight: 900; color: #1e3a8a; margin: 2px 0;">{{ number_format($tot) }}</div>
            <div style="font-size: 6.5pt; color: #2563eb; font-weight: 600;">100% Volume Pengajuan</div>
          </div>
          <div style="flex: 1; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; padding: 8px 12px;">
            <div style="font-size: 6.5pt; font-weight: 700; color: #047857; text-transform: uppercase;">2. Approved Principal</div>
            <div style="font-size: 15pt; font-weight: 900; color: #065f46; margin: 2px 0;">{{ number_format($app) }}</div>
            <div style="font-size: 6.5pt; color: #059669; font-weight: 600;">{{ $summaryRows[1]['percentage'] ?? 0 }}% Approval Rate</div>
          </div>
          <div style="flex: 1; background: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px; padding: 8px 12px;">
            <div style="font-size: 6.5pt; font-weight: 700; color: #be123c; text-transform: uppercase;">3. Rejected Principal</div>
            <div style="font-size: 15pt; font-weight: 900; color: #881337; margin: 2px 0;">{{ number_format($rej) }}</div>
            <div style="font-size: 6.5pt; color: #e11d48; font-weight: 600;">{{ $summaryRows[2]['percentage'] ?? 0 }}% Rejection Rate</div>
          </div>
        </div>
      </div>
    @elseif($chartType === 'areas')
      <div style="margin-bottom: 14px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px;">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 16px;">
          @php
            $maxSub = !empty($summaryRows) ? max(array_column($summaryRows, 'total_submitted')) : 1;
            if ($maxSub <= 0) $maxSub = 1;
          @endphp
          @foreach($summaryRows as $sr)
            @php
              $subPct = min(100, ($sr['total_submitted'] / $maxSub) * 100);
              $appPct = min(100, ($sr['approved_principal'] / $maxSub) * 100);
            @endphp
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px;">
              <div style="display: flex; justify-content: space-between; font-size: 7.5pt; font-weight: 700; margin-bottom: 3px;">
                <span class="font-mono">Area {{ $sr['area_code'] }}</span>
                <span>
                  <span style="color: #2563eb;">Submisi: {{ $sr['total_submitted'] }}</span> | 
                  <span style="color: #059669;">Approved: {{ $sr['approved_principal'] }}</span>
                </span>
              </div>
              <div style="width: 100%; background: #e2e8f0; height: 5px; border-radius: 2.5px; overflow: hidden; margin-bottom: 2px;">
                <div style="background: #2563eb; width: {{ $subPct }}%; height: 5px; border-radius: 2.5px;"></div>
              </div>
              <div style="width: 100%; background: #e2e8f0; height: 5px; border-radius: 2.5px; overflow: hidden;">
                <div style="background: #059669; width: {{ $appPct }}%; height: 5px; border-radius: 2.5px;"></div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @elseif($chartType === 'outlet_types')
      <div style="margin-bottom: 14px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px;">
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 16px;">
          @php
            $maxTot = !empty($summaryRows) ? max(array_column($summaryRows, 'total')) : 1;
            if ($maxTot <= 0) $maxTot = 1;
          @endphp
          @foreach($summaryRows as $sr)
            @php
              $barPct = min(100, ($sr['total'] / $maxTot) * 100);
            @endphp
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 8px;">
              <div style="display: flex; justify-content: space-between; font-size: 7.5pt; font-weight: 700; margin-bottom: 3px;">
                <span>{{ $sr['outlet_type'] }}</span>
                <span>
                  <span style="color: #4f46e5;">{{ $sr['total'] }} Submisi</span> | 
                  <span style="color: #059669;">{{ $sr['approved'] }} Approved</span>
                </span>
              </div>
              <div style="width: 100%; background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
                <div style="background: #6366f1; width: {{ $barPct }}%; height: 6px; border-radius: 3px;"></div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @endif
  @endif

  <!-- TABEL RINGKASAN GRAFIK -->
  <div class="section-heading">Ringkasan Statistik Angka</div>
  @if($chartType === 'comparison')
    <table>
      <thead>
        <tr>
          <th class="summary-th" style="width: 45%; text-align: left;">Metrik Ringkasan Status</th>
          <th class="summary-th" style="width: 25%; text-align: right;">Jumlah Toko (NOO)</th>
          <th class="summary-th" style="width: 30%; text-align: right;">Persentase Volume</th>
        </tr>
      </thead>
      <tbody>
        @foreach($summaryRows as $sr)
          <tr>
            <td class="font-bold">{{ $sr['label'] }}</td>
            <td class="text-right font-mono font-bold">{{ number_format($sr['count']) }}</td>
            <td class="text-right font-mono">{{ $sr['percentage'] }}%</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @elseif($chartType === 'areas')
    <table>
      <thead>
        <tr>
          <th class="summary-th" style="width: 8%;">No</th>
          <th class="summary-th" style="width: 32%; text-align: left;">Kode Area Region</th>
          <th class="summary-th" style="width: 20%; text-align: right;">Total Submisi SE</th>
          <th class="summary-th" style="width: 20%; text-align: right;">Approved Principal</th>
          <th class="summary-th" style="width: 20%; text-align: right;">Approval Rate</th>
        </tr>
      </thead>
      <tbody>
        @foreach($summaryRows as $idx => $sr)
          @php
            $rate = ($sr['total_submitted'] > 0) ? round(($sr['approved_principal'] / $sr['total_submitted']) * 100, 1) : 0;
          @endphp
          <tr>
            <td class="text-center font-mono">{{ $idx + 1 }}</td>
            <td class="font-bold font-mono">{{ $sr['area_code'] }}</td>
            <td class="text-right font-mono font-bold">{{ number_format($sr['total_submitted']) }}</td>
            <td class="text-right font-mono text-emerald-700 font-bold">{{ number_format($sr['approved_principal']) }}</td>
            <td class="text-right font-mono">{{ $rate }}%</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @elseif($chartType === 'outlet_types')
    <table>
      <thead>
        <tr>
          <th class="summary-th" style="width: 8%;">No</th>
          <th class="summary-th" style="width: 37%; text-align: left;">Tipe Outlet / Channel</th>
          <th class="summary-th" style="width: 18%; text-align: right;">Total Submisi SE</th>
          <th class="summary-th" style="width: 18%; text-align: right;">Approved Principal</th>
          <th class="summary-th" style="width: 19%; text-align: right;">Approval Rate</th>
        </tr>
      </thead>
      <tbody>
        @foreach($summaryRows as $idx => $sr)
          @php
            $rate = ($sr['total'] > 0) ? round(($sr['approved'] / $sr['total']) * 100, 1) : 0;
          @endphp
          <tr>
            <td class="text-center font-mono">{{ $idx + 1 }}</td>
            <td class="font-bold">{{ $sr['outlet_type'] }}</td>
            <td class="text-right font-mono font-bold">{{ number_format($sr['total']) }}</td>
            <td class="text-right font-mono text-emerald-700 font-bold">{{ number_format($sr['approved']) }}</td>
            <td class="text-right font-mono">{{ $rate }}%</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <!-- TABEL DETAIL DATA PENGAJUAN NOO (FIT TO WIDTH) -->
  <div class="section-heading">Tabel Detail Data Pengajuan NOO</div>
  <table>
    <thead>
      <tr>
        <th style="width: 4%;">No</th>
        <th style="width: 9%;">Region</th>
        <th style="width: 8%;">Entity</th>
        <th style="width: 18%; text-align: left;">Cabang Distributor</th>
        <th style="width: 22%; text-align: left;">Nama Toko (NOO)</th>
        <th style="width: 15%; text-align: left;">Salesman</th>
        <th style="width: 11%;">Tanggal</th>
        <th style="width: {{ $chartType === 'outlet_types' ? '8%' : '13%' }};">Status</th>
        @if($chartType === 'outlet_types')
          <th style="width: 10%; text-align: left;">Tipe Outlet</th>
        @endif
      </tr>
    </thead>
    <tbody>
      @forelse($details as $idx => $row)
        @php
          $subDate = !empty($row['submitted_at']) ? date('d/m/Y H:i', strtotime((string)$row['submitted_at'])) : '-';
          $salesmanDisplay = trim(($row['salesman_name'] ?? '') . ' (' . ($row['salesman_code'] ?? '-') . ')');
          if ($salesmanDisplay === '(-)') $salesmanDisplay = '-';
          $branchDisplay = trim(($row['branch_id'] ?? '') . ' - ' . ($row['branch_name'] ?? ''));
          $statusClass = str_contains($row['status'] ?? '', 'APPROVED') ? 'status-approved' : (str_contains($row['status'] ?? '', 'REJECTED') ? 'status-rejected' : 'status-pending');
        @endphp
        <tr>
          <td class="text-center font-mono">{{ $idx + 1 }}</td>
          <td class="text-center font-mono font-bold">{{ $row['region_code'] ?? '-' }}</td>
          <td class="text-center font-mono">{{ $row['entity_code'] ?? ($row['principal'] ?? '-') }}</td>
          <td class="text-left">{{ $branchDisplay }}</td>
          <td class="text-left font-bold">{{ $row['nama_noo'] ?? '-' }}</td>
          <td class="text-left">{{ $salesmanDisplay }}</td>
          <td class="text-center font-mono">{{ $subDate }}</td>
          <td class="text-center">
            <span class="status-badge {{ $statusClass }}">{{ $row['status'] ?? '-' }}</span>
          </td>
          @if($chartType === 'outlet_types')
            <td class="text-left">{{ $row['type_outlet'] ?? '-' }}</td>
          @endif
        </tr>
      @empty
        <tr>
          <td colspan="{{ $chartType === 'outlet_types' ? 9 : 8 }}" class="text-center" style="padding: 16px; color: #94a3b8; font-style: italic;">
            Tidak ada data pengajuan NOO yang cocok dengan filter yang dipilih.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <script>
    window.onload = function() {
      setTimeout(function() {
        window.print();
      }, 400);
    };
  </script>
</body>
</html>
