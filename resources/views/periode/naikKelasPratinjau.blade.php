@extends('adminlte::page')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Pratinjau naik kelas</h1>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <p>
                    Dari <strong>{{ $sumber->tahun_ajaran }} / {{ $sumber->semester == 1 ? 'Ganjil' : 'Genap' }}</strong>
                    ke <strong>{{ $target['tahun_ajaran'] }} / {{ $target['semester'] == 1 ? 'Ganjil' : 'Genap' }}</strong>.
                    Untuk tahun ajaran baru, kelas tujuan dipilih dari kelas yang sudah ada di periode ini. Pilihan pada baris kelas diterapkan ke semua siswa di kelas itu yang tidak lulus. Siswa yang tidak dibawa tetap ada di periode lama dan rapornya masih bisa dicetak.
                </p>
                <form method="POST" action="{{ route('dataPeriode.naikKelas.store') }}">
                    @csrf
                    <input type="hidden" name="jenis" value="{{ $jenis }}">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>NISN</th>
                                    <th>Penempatan</th>
                                    <th>Kelas tujuan</th>
                                </tr>
                            </thead>
                            @php
                                $kelompok = $baris->groupBy('kelas_asal')->sortBy(function ($anggota) {
                                    $pertama = $anggota->first();
                                    $akhir = $pertama['siswa']->sub_kelas_id ? sprintf('%02d', (int) $pertama['tingkat']) : '99';
                                    return $akhir . '-' . $pertama['kelas_asal'];
                                });
                            @endphp
                            @foreach ($kelompok as $namaKelas => $anggota)
                                @php
                                    $bisaBulk = $jenis === 'tahun_baru' && $anggota->contains(fn ($item) => (bool) $item['siswa']->sub_kelas_id);
                                    $tujuanSama = $anggota->filter(fn ($item) => $item['aksi'] !== 'lulus' && $item['kelas_tujuan_id'])->pluck('kelas_tujuan_id')->unique();
                                    $bulkAwal = $tujuanSama->count() === 1 ? $tujuanSama->first() : null;
                                @endphp
                                <tbody>
                                    <tr class="table-secondary">
                                        <td colspan="3">
                                            <strong>{{ $namaKelas }}</strong>
                                            <span class="text-muted">({{ $anggota->count() }} siswa)</span>
                                        </td>
                                        <td>
                                            @if ($bisaBulk)
                                                <select class="form-control form-control-sm bulk-tujuan" data-kelompok="{{ $loop->index }}">
                                                    <option value="">Terapkan ke semua siswa di kelas ini</option>
                                                    @foreach ($opsi_kelas as $opsi)
                                                        <option value="{{ $opsi->id }}" {{ (int) $bulkAwal === (int) $opsi->id ? 'selected' : '' }}>
                                                            {{ trim(optional($opsi->kelas)->nama_kelas . ' ' . $opsi->nama_sub_kelas) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                    @foreach ($anggota as $barisSiswa)
                                        @php $siswa = $barisSiswa['siswa']; @endphp
                                        <tr data-kelompok="{{ $loop->parent->index }}">
                                            <td>{{ $siswa->nama_siswa }}</td>
                                            <td>{{ $siswa->nisn }}</td>
                                            <td style="min-width: 220px;">
                                                @if ($jenis === 'semester')
                                                    <input type="hidden" name="aksi[{{ $siswa->id }}]" value="tetap">
                                                    Tingkat tetap
                                                @elseif (!$siswa->sub_kelas_id || $barisSiswa['tingkat'] === null)
                                                    <input type="hidden" name="aksi[{{ $siswa->id }}]" value="lulus">
                                                    Tidak dibawa
                                                @elseif ($barisSiswa['tingkat'] >= 6)
                                                    <select name="aksi[{{ $siswa->id }}]" class="form-control form-control-sm aksi-siswa" data-id="{{ $siswa->id }}">
                                                        <option value="lulus" {{ $barisSiswa['aksi'] === 'lulus' ? 'selected' : '' }}>Lulus, tidak dibawa</option>
                                                        <option value="tinggal" {{ $barisSiswa['aksi'] === 'tinggal' ? 'selected' : '' }}>Tinggal di tingkat ini</option>
                                                    </select>
                                                @else
                                                    <select name="aksi[{{ $siswa->id }}]" class="form-control form-control-sm aksi-siswa" data-id="{{ $siswa->id }}">
                                                        <option value="naik" {{ $barisSiswa['aksi'] === 'naik' ? 'selected' : '' }}>Naik tingkat</option>
                                                        <option value="tinggal" {{ $barisSiswa['aksi'] === 'tinggal' ? 'selected' : '' }}>Tinggal di tingkat ini</option>
                                                    </select>
                                                @endif
                                            </td>
                                            <td id="tujuan-{{ $siswa->id }}">
                                                @if ($jenis === 'tahun_baru' && $siswa->sub_kelas_id)
                                                    <select name="kelas_tujuan[{{ $siswa->id }}]"
                                                            class="form-control form-control-sm kelas-tujuan {{ $barisSiswa['aksi'] === 'lulus' ? 'd-none' : '' }}"
                                                            data-naik="{{ $barisSiswa['default_naik'] }}"
                                                            data-tinggal="{{ $barisSiswa['default_tinggal'] }}"
                                                            {{ $barisSiswa['aksi'] === 'lulus' ? 'disabled' : 'required' }}>
                                                        <option value="">Pilih kelas</option>
                                                        @foreach ($opsi_kelas as $opsi)
                                                            <option value="{{ $opsi->id }}" {{ (int) $barisSiswa['kelas_tujuan_id'] === (int) $opsi->id ? 'selected' : '' }}>
                                                                {{ trim(optional($opsi->kelas)->nama_kelas . ' ' . $opsi->nama_sub_kelas) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <span class="tujuan-teks {{ $barisSiswa['aksi'] === 'lulus' ? '' : 'd-none' }}">Tidak dibawa</span>
                                                @else
                                                    {{ $barisSiswa['kelas_tujuan'] }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endforeach
                        </table>
                    </div>
                    <button type="submit" class="btn bg-gradient-green" onclick="return confirm('Salin biodata ke periode baru dan aktifkan periode itu? Nilai lama tidak dibawa.')">Siapkan dan aktifkan</button>
                    <a href="{{ route('dataPeriode.naikKelas') }}" class="btn btn-secondary">Kembali</a>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $('.aksi-siswa').on('change', function () {
            var tujuan = $('#tujuan-' + $(this).data('id'));
            var pilih = tujuan.find('select');
            var teks = tujuan.find('.tujuan-teks');
            if (!pilih.length) {
                return;
            }
            if ($(this).val() === 'lulus') {
                pilih.addClass('d-none').prop('disabled', true).prop('required', false);
                teks.removeClass('d-none');
                return;
            }
            pilih.removeClass('d-none').prop('disabled', false).prop('required', true);
            teks.addClass('d-none');
            var kelompok = tujuan.closest('tr').data('kelompok');
            var bulk = $('.bulk-tujuan[data-kelompok="' + kelompok + '"]').val();
            pilih.val(bulk || pilih.data($(this).val()) || '');
        });

        $('.bulk-tujuan').on('change', function () {
            var nilai = $(this).val();
            if (!nilai) {
                return;
            }
            $('tr[data-kelompok="' + $(this).data('kelompok') + '"] select.kelas-tujuan:not(:disabled)').val(nilai);
        });
    </script>
@stop
