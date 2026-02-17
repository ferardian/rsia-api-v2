<x-print-layout>
    <x-slot name="title">
        SK Jadwal Praktik HFIS
    </x-slot>

    @push('styles')
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.25;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }
        .header-img {
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: -1;
        }
        .footer-img {
            width: 100%;
            position: fixed;
            bottom: 0;
            left: 0;
            z-index: -1;
        }
        .content-wrapper {
            margin: 0;
            padding: 3.5cm 1.5cm 2.2cm 1.5cm;
            position: relative;
        }
        /* ... existing styles ... */
        .title {
            text-align: center;
            text-decoration: underline;
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 2px;
        }
        .subtitle {
            text-align: center;
            font-weight: bold;
            font-size: 13pt;
            margin-bottom: 12px;
        }
        .pic-info {
            margin-bottom: 10px;
        }
        .pic-info table {
            width: 100%;
        }
        .pic-info td {
            vertical-align: top;
            padding: 1px 0;
        }
        .label {
            width: 130px;
        }
        .colon {
            width: 10px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            table-layout: fixed;
        }
        .schedule-table th, .schedule-table td {
            border: 1px solid black;
            padding: 3px 1px;
            text-align: center;
            font-size: 7.5pt;
            word-wrap: break-word;
            overflow: hidden;
        }
        .schedule-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .bg-green-light {
            background-color: #e5f4e5;
        }
        /* Column Widths (Must sum to 100%) */
        .col-hari { width: 14%; }
        .col-child { width: 14.33%; }

        .signature-section {
            width: 100%;
            margin-top: 15px;
        }
        .signature-table {
            width: 100%;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .signature-space {
            height: 90px;
            position: relative;
            margin: 0;
        }
        .signature-img {
            max-height: 90px;
            max-width: 160px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
        }
        .notes {
            font-size: 7.5pt;
            color: red;
            margin-bottom: 8px;
            font-style: italic;
        }
        .list-numbered {
            margin-left: 15px;
            margin-bottom: 5px;
        }
        .list-item {
            margin-bottom: 2px;
            text-align: justify;
            font-size: 10.5pt;
        }
        p {
            text-align: justify;
            margin-bottom: 8px;
        }
    </style>
    @endpush

    <!-- Header & Footer -->
    <img src="{{ public_path('assets/images/kop-surat/header.png') }}" class="header-img">
    <img src="{{ public_path('assets/images/kop-surat/footer.png') }}" class="footer-img">

    @foreach($items as $data)
    <div class="content-wrapper {{ !$loop->last ? 'page-break' : '' }}">
        <div class="title">SURAT KETERANGAN JADWAL PRAKTIK</div>
        <div class="subtitle">PADA APLIKASI HFIS VERSI 6.1</div>

        <div class="pic-info">
            Saya yang bertanda tangan di bawah ini:<br>
            <table>
                <tr>
                    <td class="label">Nama Lengkap</td>
                    <td class="colon">:</td>
                    <td>{{ $data->nama_pic }}</td>
                </tr>
                <tr>
                    <td class="label">Jabatan</td>
                    <td class="colon">:</td>
                    <td>{{ $data->jabatan_pic }}</td>
                </tr>
                <tr>
                    <td class="label">Nama Faskes</td>
                    <td class="colon">:</td>
                    <td>RSIA Aisyiyah Pekajangan</td>
                </tr>
                <tr>
                    <td class="label">Alamat Faskes</td>
                    <td class="colon">:</td>
                    <td>Jl. Raya Pekajangan No.610, Kabupaten Pekalongan</td>
                </tr>
            </table>
        </div>

        <div>
            Menerangkan bahwa:<br>
            <div class="list-numbered">
                <div class="list-item">1. Jadwal praktik DPJP {{ $data->dokter->nm_dokter }} sebagai berikut:</div>
            </div>

            <table class="schedule-table">
                <thead>
                    <tr>
                        <th class="col-hari" rowspan="2">Hari Pelayanan</th>
                        <th class="col-sip" colspan="2">SIP 1: {{ $data->faskes_sip1 ?: '...' }}</th>
                        <th class="col-sip" colspan="2">SIP 2: {{ $data->faskes_sip2 ?: '...' }}</th>
                        <th class="col-sip" colspan="2">SIP 3: {{ $data->faskes_sip3 ?: '...' }}</th>
                    </tr>
                    <tr>
                        <th class="bg-green-light">Jam Kerja *)</th>
                        <th class="bg-green-light">Praktek Poli</th>
                        <th class="bg-green-light">Jam Kerja *)</th>
                        <th class="bg-green-light">Praktek Poli</th>
                        <th class="bg-green-light">Jam Kerja *)</th>
                        <th class="bg-green-light">Praktek Poli</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data->detail as $row)
                    @if($row->hari != 'Cuti/Libur')
                    <tr>
                        <td style="font-weight: bold;">{{ $row->hari }}</td>
                        <td>{!! nl2br(e($row->sip1_jam_kerja)) !!}</td>
                        <td>{!! nl2br(e($row->sip1_jam_praktek)) !!}</td>
                        <td>{!! nl2br(e($row->sip2_jam_kerja)) !!}</td>
                        <td>{!! nl2br(e($row->sip2_jam_praktek)) !!}</td>
                        <td>{!! nl2br(e($row->sip3_jam_kerja)) !!}</td>
                        <td>{!! nl2br(e($row->sip3_jam_praktek)) !!}</td>
                    </tr>
                    @endif
                    @endforeach
                    
                    @php $cuti = $data->detail->where('hari', 'Cuti/Libur')->first(); @endphp
                    <tr>
                        <td style="font-size: 7.5pt;">Cuti Bersama / Libur Nasional</td>
                        <td>{{ $cuti ? $cuti->sip1_jam_kerja : '' }}</td>
                        <td>{{ $cuti ? $cuti->sip1_jam_praktek : '' }}</td>
                        <td>{{ $cuti ? $cuti->sip2_jam_kerja : '' }}</td>
                        <td>{{ $cuti ? $cuti->sip2_jam_praktek : '' }}</td>
                        <td>{{ $cuti ? $cuti->sip3_jam_kerja : '' }}</td>
                        <td>{{ $cuti ? $cuti->sip3_jam_praktek : '' }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="notes">
                *) Jika SIP dokter terdaftar di RS Pemerintah (RSUD) maka jam kerja diisi sesuai dengan ketentuan ASN di wilayah Kabupaten/Kota.
            </div>

            <div class="list-numbered">
                <div class="list-item">2. Jadwal tersebut tidak saling beririsan dan telah mempertimbangkan waktu tempuh yang diperlukan antar tempat praktik atau antar FKRTL.</div>
                <div class="list-item">3. Jadwal praktik poli adalah jam duduk dokter di poliklinik rawat jalan.</div>
                <div class="list-item">4. Saya bertanggungjawab apabila dikemudian hari ditemukan hal-hal yang tidak sesuai dengan surat keterangan ini.</div>
            </div>

            <p style="text-align: justify; margin-top: 10px; font-size: 10.5pt; text-indent: 1cm;">
                Demikian surat pernyataan ini saya buat dengan sesungguhnya, saya bertanggungjawab apabila dikemudian hari ditemukan hal yang tidak sesuai dengan Surat Keterangan ini.
            </p>

            <div class="signature-section">
                <div style="text-align: right; margin-bottom: 5px; margin-right: 20px;">
                    Pekalongan, {{ \Carbon\Carbon::parse($data->tgl_surat)->isoFormat('D MMMM Y') }}
                </div>
                <table class="signature-table">
                    <tr>
                        <td>
                            Mengetahui<br>
                            Direktur RSIA Aisyiyah Pekajangan
                            <div class="signature-space">
                                @php 
                                    $ttdPath = public_path('assets/images/ttd/direktur_hfis.png');
                                @endphp
                                @if(file_exists($ttdPath))
                                    <img src="{{ $ttdPath }}" class="signature-img">
                                @endif
                            </div>
                            <strong><u>dr. Wididah Kadir</u></strong>
                        </td>
                        <td>
                            <br>
                            DPJP
                            <div class="signature-space"></div>
                            <strong><u>{{ $data->dokter->nm_dokter }}</u></strong>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @endforeach
</x-print-layout>
