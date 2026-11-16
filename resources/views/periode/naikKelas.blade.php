@extends('adminlte::page')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Siapkan semester berikutnya</h1>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        @if (session('naik_kelas_gagal'))
            <div class="alert alert-danger">{{ session('naik_kelas_gagal') }}</div>
        @endif
        <div class="card">
            <div class="card-body">
                <p>
                    Periode yang sedang berjalan:
                    <strong>{{ $sumber->tahun_ajaran }} / {{ $sumber->semester == 1 ? 'Ganjil' : 'Genap' }}</strong>.
                    Biodata siswa, kelas, dan kurikulum disalin. Nilai semester baru kosong. Rapor periode ini tetap tersimpan.
                </p>
                <form method="POST" action="{{ route('dataPeriode.naikKelas.preview') }}">
                    @csrf
                    <div class="form-group">
                        <label for="jenis">Jenis pergantian</label>
                        <select name="jenis" id="jenis" class="form-control" required>
                            @if ($bolehSemester)
                                <option value="semester">Semester genap, tingkat kelas tetap</option>
                            @endif
                            <option value="tahun_baru">Tahun ajaran baru, siswa naik tingkat</option>
                        </select>
                    </div>
                    <button type="submit" class="btn bg-gradient-green">Lanjut ke pratinjau</button>
                    <a href="{{ route('dataPeriode.index') }}" class="btn btn-secondary">Batal</a>
                </form>
            </div>
        </div>
    </div>
@stop
