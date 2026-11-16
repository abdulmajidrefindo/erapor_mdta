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
                    Siswa yang tidak dibawa tetap ada di periode lama dan rapornya masih bisa dicetak.
                </p>
                <form method="POST" action="{{ route('dataPeriode.naikKelas.store') }}">
                    @csrf
                    <input type="hidden" name="jenis" value="{{ $jenis }}">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>NISN</th>
                                    <th>Kelas sekarang</th>
                                    <th>Penempatan</th>
                                    <th>Kelas tujuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($baris as $barisSiswa)
                                    @php $siswa = $barisSiswa['siswa']; @endphp
                                    <tr>
                                        <td>{{ $siswa->nama_siswa }}</td>
                                        <td>{{ $siswa->nisn }}</td>
                                        <td>{{ $barisSiswa['kelas_asal'] }}</td>
                                        <td style="min-width: 220px;">
                                            @if ($jenis === 'semester')
                                                <input type="hidden" name="aksi[{{ $siswa->id }}]" value="tetap">
                                                Tingkat tetap
                                            @elseif (!$siswa->sub_kelas_id || $barisSiswa['tingkat'] === null)
                                                <input type="hidden" name="aksi[{{ $siswa->id }}]" value="lulus">
                                                Tidak dibawa
                                            @elseif ($barisSiswa['tingkat'] >= 6)
                                                <select name="aksi[{{ $siswa->id }}]" class="form-control form-control-sm aksi-siswa" data-id="{{ $siswa->id }}">
                                                    <option value="lulus" selected>Lulus, tidak dibawa</option>
                                                    <option value="tinggal">Tinggal di tingkat ini</option>
                                                </select>
                                            @else
                                                <select name="aksi[{{ $siswa->id }}]" class="form-control form-control-sm aksi-siswa" data-id="{{ $siswa->id }}">
                                                    <option value="naik" selected>Naik tingkat</option>
                                                    <option value="tinggal">Tinggal di tingkat ini</option>
                                                </select>
                                            @endif
                                        </td>
                                        <td id="tujuan-{{ $siswa->id }}"
                                            data-naik="{{ $barisSiswa['kelas_tujuan'] }}"
                                            data-tinggal="{{ $barisSiswa['kelas_asal'] }}"
                                            data-lulus="Tidak dibawa">
                                            {{ $barisSiswa['kelas_tujuan'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
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
            tujuan.text(tujuan.data($(this).val()));
        });
    </script>
@stop
