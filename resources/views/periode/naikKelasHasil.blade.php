@extends('adminlte::page')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1 class="m-0">Periode baru aktif</h1>
        </div>
    </div>
@stop

@section('content')
    <div class="container-fluid">
        <div class="alert alert-success">
            {{ $periode->tahun_ajaran }} / {{ $periode->semester == 1 ? 'Ganjil' : 'Genap' }} sekarang aktif.
            {{ $disalin }} siswa disalin{{ $tinggal ? ', termasuk ' . $tinggal . ' yang tinggal di tingkatnya' : '' }}.
            Nilai semester baru masih kosong.
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Siswa yang tidak dibawa</h3>
            </div>
            <div class="card-body">
                <p>
                    Mereka tetap ada di periode lama, jadi rapor kelulusan masih bisa dicetak dari menu Rapor dengan memilih periode itu.
                    Hapus hanya jika rapor lama tidak diperlukan lagi. Menghapus siswa juga menghapus nilai dan rapor lamanya.
                </p>
                @if (count($tidak_dibawa) === 0)
                    <p class="mb-0">Semua siswa dibawa ke periode baru.</p>
                @else
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>NISN</th>
                                <th>Kelas lama</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tidak_dibawa as $siswa)
                                <tr>
                                    <td>{{ $siswa->nama_siswa }}</td>
                                    <td>{{ $siswa->nisn }}</td>
                                    <td>
                                        {{ $siswa->sub_kelas ? trim($siswa->sub_kelas->kelas->nama_kelas . ' ' . $siswa->sub_kelas->nama_sub_kelas) : '-' }}
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger hapus-lulus" data-id="{{ $siswa->id }}" data-nama="{{ $siswa->nama_siswa }}">Hapus siswa dan rapor lama</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
                <a href="{{ route('dataPeriode.index') }}" class="btn btn-secondary mt-3">Ke data tahun pelajaran</a>
                <a href="{{ route('raporSiswa.index') }}" class="btn bg-gradient-green mt-3">Ke rapor</a>
            </div>
        </div>
    </div>
@stop

@section('js')
    <script>
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });
        $('.hapus-lulus').on('click', function () {
            var tombol = $(this);
            Swal.fire({
                title: 'Hapus ' + tombol.data('nama') + '?',
                text: 'Menghapus siswa juga menghapus nilai dan rapor lamanya. Data tidak dapat dikembalikan. Biarkan siswa di periode lama jika rapor kelulusan masih perlu dicetak.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal'
            }).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }
                $.ajax({
                    type: 'DELETE',
                    url: '{{ url('/dataSiswa') }}/' + tombol.data('id'),
                    success: function (response) {
                        if (response.success) {
                            tombol.closest('tr').remove();
                        }
                    }
                });
            });
        });
    </script>
@stop
