<?php

namespace App\Services;

use App\Models\Doa1;
use App\Models\Guru;
use App\Models\Hadist1;
use App\Models\IbadahHarian1;
use App\Models\IlmanWaaRuuhan;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\SiswaBidangStudi;
use App\Models\SiswaDoa;
use App\Models\SiswaHadist;
use App\Models\SiswaIbadahHarian;
use App\Models\SiswaIlmanWaaRuuhan;
use App\Models\SiswaTahfidz;
use App\Models\SubKelas;
use App\Models\Tahfidz1;
use App\Models\UserRoles;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NaikKelasService
{
    public function sumberAktif(): Periode
    {
        $periode = Periode::where('status', 'aktif')->first();
        if (!$periode) {
            throw new InvalidArgumentException('Tidak ada periode aktif.');
        }

        return $periode;
    }

    public function targetDari(Periode $sumber, string $jenis): array
    {
        $bagian = explode('/', $sumber->tahun_ajaran);
        if (count($bagian) !== 2) {
            throw new InvalidArgumentException('Format tahun ajaran periode sumber tidak dikenali.');
        }

        if ($jenis === 'semester') {
            if ((int) $sumber->semester !== 1) {
                throw new InvalidArgumentException('Semester genap hanya bisa dilanjutkan ke tahun ajaran baru.');
            }

            return [
                'tahun_ajaran' => $sumber->tahun_ajaran,
                'semester' => 2,
                'naik_tingkat' => false,
            ];
        }

        if ($jenis !== 'tahun_baru') {
            throw new InvalidArgumentException('Jenis pergantian periode tidak dikenali.');
        }

        $tahunBerikut = (int) $bagian[1];

        return [
            'tahun_ajaran' => $tahunBerikut . '/' . ($tahunBerikut + 1),
            'semester' => 1,
            'naik_tingkat' => true,
        ];
    }

    public function pratinjau(Periode $sumber, string $jenis): array
    {
        $target = $this->targetDari($sumber, $jenis);
        $siswa = Siswa::with('sub_kelas.kelas')
            ->where('periode_id', $sumber->id)
            ->orderBy('nama_siswa')
            ->get();

        $baris = $siswa->map(function (Siswa $item) use ($target) {
            $tingkat = $this->tingkatDariKelas(optional($item->sub_kelas)->kelas);
            $aksi = $this->aksiAwal($tingkat, $target['naik_tingkat'], $item->sub_kelas_id);

            return [
                'siswa' => $item,
                'tingkat' => $tingkat,
                'aksi' => $aksi,
                'kelas_asal' => $item->sub_kelas
                    ? trim($item->sub_kelas->kelas->nama_kelas . ' ' . $item->sub_kelas->nama_sub_kelas)
                    : 'Belum punya kelas',
                'kelas_tujuan' => $this->labelTujuan($item, $aksi),
            ];
        });

        return [
            'sumber' => $sumber,
            'target' => $target,
            'jenis' => $jenis,
            'baris' => $baris,
        ];
    }

    public function jalankan(Periode $sumber, string $jenis, array $aksiPerSiswa): array
    {
        $targetInfo = $this->targetDari($sumber, $jenis);

        return DB::transaction(function () use ($sumber, $jenis, $aksiPerSiswa, $targetInfo) {
            $target = Periode::where('tahun_ajaran', $targetInfo['tahun_ajaran'])
                ->where('semester', $targetInfo['semester'])
                ->first();

            if ($target && SubKelas::where('periode_id', $target->id)->exists()) {
                throw new InvalidArgumentException('Periode tujuan sudah berisi kelas. Ganti periode tidak dijalankan ulang.');
            }

            if (!$target) {
                $target = Periode::create([
                    'tahun_ajaran' => $targetInfo['tahun_ajaran'],
                    'semester' => $targetInfo['semester'],
                    'status' => 'tidak aktif',
                ]);
            }

            $siswaSumber = Siswa::with('sub_kelas.kelas')
                ->where('periode_id', $sumber->id)
                ->get();

            $keputusan = [];
            foreach ($siswaSumber as $siswa) {
                $tingkat = $this->tingkatDariKelas(optional($siswa->sub_kelas)->kelas);
                $aksi = $aksiPerSiswa[$siswa->id] ?? $this->aksiAwal($tingkat, $targetInfo['naik_tingkat'], $siswa->sub_kelas_id);
                $this->pastikanAksiSah($aksi, $tingkat, $targetInfo['naik_tingkat'], $siswa->sub_kelas_id);
                $keputusan[$siswa->id] = $aksi;
            }

            $subTujuan = [];
            $kelasTujuanDipakai = [];

            foreach ($siswaSumber as $siswa) {
                $aksi = $keputusan[$siswa->id];
                if ($aksi === 'lulus' || !$siswa->sub_kelas) {
                    continue;
                }

                $kelasTujuan = $this->kelasTujuan($siswa->sub_kelas->kelas, $aksi);
                $kunciSub = $siswa->sub_kelas_id . ':' . $kelasTujuan->id;
                if (!isset($subTujuan[$kunciSub])) {
                    $subTujuan[$kunciSub] = $this->salinSubKelas($siswa->sub_kelas, $kelasTujuan, $target);
                }
                $kelasTujuanDipakai[$kelasTujuan->id] = true;
            }

            foreach (array_keys($kelasTujuanDipakai) as $kelasId) {
                $this->salinKurikulum($sumber, $target, $kelasId);
            }

            $disalin = 0;
            $tinggal = 0;
            $tidakDibawa = [];

            foreach ($siswaSumber as $siswa) {
                $aksi = $keputusan[$siswa->id];
                if ($aksi === 'lulus' || !$siswa->sub_kelas) {
                    $tidakDibawa[] = $siswa;
                    continue;
                }

                $kelasTujuan = $this->kelasTujuan($siswa->sub_kelas->kelas, $aksi);
                $subBaru = $subTujuan[$siswa->sub_kelas_id . ':' . $kelasTujuan->id];
                $baru = Siswa::create([
                    'nisn' => $siswa->nisn,
                    'nama_siswa' => $siswa->nama_siswa,
                    'orangtua_wali' => $siswa->orangtua_wali,
                    'sub_kelas_id' => $subBaru->id,
                    'periode_id' => $target->id,
                    'rapor_siswa_id' => $siswa->rapor_siswa_id ?: 1,
                ]);
                $this->buatSlotNilaiKosong($baru, $kelasTujuan->id, $target);
                $disalin++;
                if ($aksi === 'tinggal') {
                    $tinggal++;
                }
            }

            $this->angkatWaliBaru($target);
            $this->aktifkan($target);

            return [
                'periode' => $target->fresh(),
                'disalin' => $disalin,
                'tinggal' => $tinggal,
                'tidak_dibawa' => $tidakDibawa,
            ];
        });
    }

    private function aksiAwal(?int $tingkat, bool $naikTingkat, $subKelasId): string
    {
        if (!$subKelasId || $tingkat === null) {
            return 'lulus';
        }
        if (!$naikTingkat) {
            return 'tetap';
        }
        if ($tingkat >= 6) {
            return 'lulus';
        }

        return 'naik';
    }

    private function pastikanAksiSah(string $aksi, ?int $tingkat, bool $naikTingkat, $subKelasId): void
    {
        if (!$subKelasId || $tingkat === null) {
            if ($aksi !== 'lulus') {
                throw new InvalidArgumentException('Siswa tanpa kelas tidak dapat dibawa ke periode baru.');
            }

            return;
        }

        if (!$naikTingkat && $aksi !== 'tetap') {
            throw new InvalidArgumentException('Pergantian semester hanya menempatkan siswa di tingkat yang sama.');
        }

        if ($naikTingkat && $tingkat >= 6 && !in_array($aksi, ['lulus', 'tinggal'], true)) {
            throw new InvalidArgumentException('Siswa kelas terakhir hanya dapat lulus atau tinggal.');
        }

        if ($naikTingkat && $tingkat < 6 && !in_array($aksi, ['naik', 'tinggal'], true)) {
            throw new InvalidArgumentException('Siswa hanya dapat naik atau tinggal di tingkat yang sama.');
        }
    }

    private function kelasTujuan(Kelas $asal, string $aksi): Kelas
    {
        if ($aksi === 'naik') {
            $berikut = $this->kelasPadaTingkat($this->tingkatDariKelas($asal) + 1);
            if (!$berikut) {
                throw new InvalidArgumentException('Tingkat berikutnya setelah ' . $asal->nama_kelas . ' tidak ada.');
            }

            return $berikut;
        }

        return $asal;
    }

    private function labelTujuan(Siswa $siswa, string $aksi): string
    {
        if ($aksi === 'lulus' || !$siswa->sub_kelas) {
            return 'Tidak dibawa';
        }

        $rombel = $siswa->sub_kelas->nama_sub_kelas;
        if ($aksi === 'naik') {
            $berikut = $this->kelasPadaTingkat($this->tingkatDariKelas($siswa->sub_kelas->kelas) + 1);

            return $berikut ? trim($berikut->nama_kelas . ' ' . $rombel) : 'Tingkat berikutnya tidak ada';
        }

        return trim($siswa->sub_kelas->kelas->nama_kelas . ' ' . $rombel);
    }

    private function tingkatDariKelas(?Kelas $kelas): ?int
    {
        if (!$kelas || !preg_match('/(\d+)/', $kelas->nama_kelas, $cocok)) {
            return null;
        }

        return (int) $cocok[1];
    }

    private function kelasPadaTingkat(int $tingkat): ?Kelas
    {
        return Kelas::all()->first(function (Kelas $kelas) use ($tingkat) {
            return $this->tingkatDariKelas($kelas) === $tingkat;
        });
    }

    private function salinSubKelas(SubKelas $asal, Kelas $kelasTujuan, Periode $target): SubKelas
    {
        $ada = SubKelas::where('periode_id', $target->id)
            ->where('kelas_id', $kelasTujuan->id)
            ->where('nama_sub_kelas', $asal->nama_sub_kelas)
            ->first();
        if ($ada) {
            return $ada;
        }

        return SubKelas::create([
            'nama_sub_kelas' => $asal->nama_sub_kelas,
            'kelas_id' => $kelasTujuan->id,
            'periode_id' => $target->id,
            'guru_id' => $asal->guru_id,
            'catatan_sub_kelas' => null,
        ]);
    }

    private function salinKurikulum(Periode $sumber, Periode $target, int $kelasId): void
    {
        Mapel::where('periode_id', $sumber->id)->where('kelas_id', $kelasId)->get()
            ->each(function (Mapel $item) use ($target, $kelasId) {
                Mapel::create([
                    'nama_mapel' => $item->nama_mapel,
                    'guru_id' => $item->guru_id,
                    'kelas_id' => $kelasId,
                    'periode_id' => $target->id,
                ]);
            });

        foreach ([Hadist1::class, Doa1::class, Tahfidz1::class] as $model) {
            $model::where('periode_id', $sumber->id)->where('kelas_id', $kelasId)->get()
                ->each(function ($item) use ($model, $target, $kelasId) {
                    $model::create([
                        'nama_nilai' => $item->nama_nilai,
                        'guru_id' => $item->guru_id,
                        'kelas_id' => $kelasId,
                        'periode_id' => $target->id,
                    ]);
                });
        }

        IbadahHarian1::where('periode_id', $sumber->id)->where('kelas_id', $kelasId)->get()
            ->each(function (IbadahHarian1 $item) use ($target, $kelasId) {
                IbadahHarian1::create([
                    'nama_kriteria' => $item->nama_kriteria,
                    'guru_id' => $item->guru_id,
                    'kelas_id' => $kelasId,
                    'periode_id' => $target->id,
                ]);
            });

        IlmanWaaRuuhan::where('periode_id', $sumber->id)->where('kelas_id', $kelasId)->get()
            ->each(function (IlmanWaaRuuhan $item) use ($target, $kelasId) {
                IlmanWaaRuuhan::create([
                    'pencapaian' => $item->pencapaian,
                    'guru_id' => $item->guru_id,
                    'kelas_id' => $kelasId,
                    'periode_id' => $target->id,
                ]);
            });
    }

    private function buatSlotNilaiKosong(Siswa $siswa, int $kelasId, Periode $periode): void
    {
        Tahfidz1::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (Tahfidz1 $item) use ($siswa, $periode) {
                SiswaTahfidz::create([
                    'siswa_id' => $siswa->id,
                    'tahfidz_1_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                    'penilaian_huruf_angka_id' => 101,
                ]);
            });

        Doa1::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (Doa1 $item) use ($siswa, $periode) {
                SiswaDoa::create([
                    'siswa_id' => $siswa->id,
                    'doa_1_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                    'penilaian_huruf_angka_id' => 101,
                ]);
            });

        Hadist1::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (Hadist1 $item) use ($siswa, $periode) {
                SiswaHadist::create([
                    'siswa_id' => $siswa->id,
                    'hadist_1_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                    'penilaian_huruf_angka_id' => 101,
                ]);
            });

        IbadahHarian1::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (IbadahHarian1 $item) use ($siswa, $periode) {
                SiswaIbadahHarian::create([
                    'siswa_id' => $siswa->id,
                    'ibadah_harian_1_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                    'penilaian_deskripsi_id' => 5,
                ]);
            });

        IlmanWaaRuuhan::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (IlmanWaaRuuhan $item) use ($siswa, $periode) {
                SiswaIlmanWaaRuuhan::create([
                    'siswa_id' => $siswa->id,
                    'ilman_waa_ruuhan_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                    'penilaian_huruf_angka_id' => 101,
                    'penilaian_deskripsi_id' => 5,
                    'jilid' => '0',
                    'halaman' => 0,
                ]);
            });

        Mapel::where('kelas_id', $kelasId)->where('periode_id', $periode->id)->get()
            ->each(function (Mapel $item) use ($siswa, $periode) {
                SiswaBidangStudi::create([
                    'siswa_id' => $siswa->id,
                    'mapel_id' => $item->id,
                    'profil_sekolah_id' => 1,
                    'nilai_uh_1' => 101,
                    'nilai_uh_2' => 101,
                    'nilai_uh_3' => 101,
                    'nilai_uh_4' => 101,
                    'nilai_tugas_1' => 101,
                    'nilai_tugas_2' => 101,
                    'nilai_uts' => 101,
                    'nilai_pas' => 101,
                    'nilai_akhir' => 101,
                    'periode_id' => $periode->id,
                    'rapor_siswa_id' => 1,
                ]);
            });
    }

    private function angkatWaliBaru(Periode $target): void
    {
        $guruIds = SubKelas::where('periode_id', $target->id)->whereNotNull('guru_id')->pluck('guru_id')->unique();
        foreach ($guruIds as $guruId) {
            $guru = Guru::find($guruId);
            if (!$guru) {
                continue;
            }
            $peran = UserRoles::where('user_id', $guru->user_id)->first();
            if ($peran && (int) $peran->role_id === 3) {
                $peran->role_id = 2;
                $peran->save();
            }
        }
    }

    private function aktifkan(Periode $target): void
    {
        Periode::where('id', '!=', $target->id)->update(['status' => 'tidak aktif']);
        $target->status = 'aktif';
        $target->save();
    }
}
