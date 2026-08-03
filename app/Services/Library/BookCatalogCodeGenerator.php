<?php

namespace App\Services\Library;

use App\Models\Item;
use Illuminate\Support\Str;

class BookCatalogCodeGenerator
{
    /**
     * Pemetaan kata kunci ke kelompok klasifikasi DDC yang paling umum
     * digunakan pada koleksi sekolah dasar.
     *
     * @var array<string, array<int, string>>
     */
    private const CLASSIFICATION_RULES = [
        '005.1' => [
            'pemrograman', 'programming', 'coding', 'algoritma', 'struktur data',
            'pengembangan web', 'web development', 'website', 'html', 'css',
            'javascript', 'php', 'laravel', 'python', 'java', 'software',
            'perangkat lunak',
        ],
        '004.678' => ['internet', 'jaringan internet', 'dunia maya'],
        '004' => [
            'komputer', 'informatika', 'teknologi informasi', 'tik', 'digital',
            'jaringan komputer', 'database', 'basis data',
        ],
        '020' => ['perpustakaan', 'ilmu perpustakaan', 'pustakawan'],
        '150' => ['psikologi', 'kepribadian', 'emosi', 'kecerdasan emosional'],
        '100' => ['filsafat', 'logika', 'etika'],
        '297' => ['islam', 'alquran', 'al quran', 'quran', 'hadis', 'fiqih', 'akidah', 'akhlak'],
        '282' => ['katolik'],
        '230' => ['kristen', 'alkitab', 'injil'],
        '294.5' => ['hindu'],
        '294.3' => ['buddha', 'budha'],
        '200' => ['agama', 'keagamaan'],
        '323.6' => ['ppkn', 'pkn', 'pancasila', 'kewarganegaraan'],
        '330' => ['ekonomi', 'perdagangan', 'keuangan'],
        '340' => ['hukum', 'undang undang'],
        '499.221' => ['bahasa indonesia', 'tata bahasa indonesia', 'kamus indonesia'],
        '420' => ['bahasa inggris', 'english', 'grammar', 'vocabulary'],
        '410' => ['bahasa', 'linguistik', 'tata bahasa', 'kamus'],
        '510' => ['matematika', 'math', 'berhitung', 'aritmetika', 'aljabar', 'geometri'],
        '520' => ['astronomi', 'tata surya', 'planet', 'bintang', 'angkasa'],
        '530' => ['fisika', 'mekanika', 'listrik', 'magnet', 'cahaya', 'bunyi'],
        '540' => ['kimia', 'unsur', 'senyawa'],
        '570' => ['biologi', 'makhluk hidup', 'hewan', 'tumbuhan', 'anatomi'],
        '500' => ['ipa', 'ipas', 'ilmu pengetahuan alam', 'sains', 'science'],
        '610' => ['kesehatan', 'kedokteran', 'penyakit', 'gizi', 'tubuh manusia'],
        '620' => ['teknik', 'mesin', 'rekayasa', 'engineering'],
        '630' => ['pertanian', 'perkebunan', 'peternakan', 'perikanan'],
        '640' => ['rumah tangga', 'memasak', 'resep', 'kerajinan rumah'],
        '600' => ['teknologi', 'terapan'],
        '741.5' => ['komik', 'kartun'],
        '780' => ['musik', 'lagu', 'alat musik'],
        '796' => ['olahraga', 'pjok', 'penjaskes', 'sepak bola', 'bulu tangkis', 'renang', 'senam'],
        '700' => ['seni', 'sbdp', 'menggambar', 'melukis', 'tari', 'teater', 'kerajinan'],
        '899.221' => [
            'novel', 'cerpen', 'cerita pendek', 'dongeng', 'fabel', 'puisi',
            'sastra indonesia', 'cerita rakyat',
        ],
        '800' => ['sastra', 'kesusastraan'],
        '910' => ['geografi', 'atlas', 'peta', 'perjalanan', 'pariwisata'],
        '920' => ['biografi', 'autobiografi', 'tokoh'],
        '900' => ['sejarah', 'zaman dahulu', 'peristiwa sejarah'],
        '370' => ['pendidikan', 'pembelajaran', 'pengajaran', 'kurikulum', 'tematik', 'guru'],
        '300' => ['ips', 'ilmu pengetahuan sosial', 'sosiologi', 'masyarakat'],
    ];

    /**
     * @param array<int, string> $authors
     * @return array{classification_code: string, call_number: string}
     */
    public function generate(Item $book, array $authors): array
    {
        return $this->generateFromValues(
            (string) $book->item_name,
            $book->description,
            $book->category?->category_code,
            $book->category?->category_name,
            $book->category?->description,
            $authors
        );
    }

    /**
     * @param array<int, string> $authors
     * @return array{classification_code: string, call_number: string}
     */
    public function generateFromValues(
        string $title,
        ?string $description,
        ?string $categoryCode,
        ?string $categoryName,
        ?string $categoryDescription,
        array $authors
    ): array
    {
        $classificationCode = $this->classificationCodeFromValues(
            $title,
            $description,
            $categoryCode,
            $categoryName,
            $categoryDescription
        );

        return [
            'classification_code' => $classificationCode,
            'call_number' => $this->callNumber(
                $classificationCode,
                $authors[0] ?? null,
                $title
            ),
        ];
    }

    public function classificationCode(Item $book): string
    {
        return $this->classificationCodeFromValues(
            (string) $book->item_name,
            $book->description,
            $book->category?->category_code,
            $book->category?->category_name,
            $book->category?->description
        );
    }

    public function classificationCodeFromValues(
        string $title,
        ?string $description,
        ?string $categoryCode,
        ?string $categoryName,
        ?string $categoryDescription
    ): string
    {
        $categoryCode = trim((string) $categoryCode);

        // Jika admin menggunakan kode kategori berbentuk DDC, kode tersebut
        // menjadi sumber utama dan tidak ditimpa oleh pencocokan kata kunci.
        if (preg_match('/^\d{3}(?:\.\d+)?$/', $categoryCode) === 1) {
            return $categoryCode;
        }

        $searchText = $this->normalize(implode(' ', array_filter([
            $title,
            $description,
            $categoryName,
            $categoryDescription,
        ])));

        foreach (self::CLASSIFICATION_RULES as $classificationCode => $keywords) {
            foreach ($keywords as $keyword) {
                if ($this->containsPhrase($searchText, $this->normalize($keyword))) {
                    return $classificationCode;
                }
            }
        }

        return '000';
    }

    public function callNumber(string $classificationCode, ?string $firstAuthor, string $title): string
    {
        return sprintf(
            '%s %s %s',
            $classificationCode,
            $this->authorMark($firstAuthor),
            $this->titleMark($title)
        );
    }

    private function authorMark(?string $author): string
    {
        $normalized = strtoupper(Str::ascii((string) $author));
        $normalized = preg_replace('/[^A-Z0-9\s]+/', ' ', $normalized) ?: '';

        $ignoredParts = [
            'DR', 'DRA', 'DRS', 'PROF', 'IR', 'H', 'HJ',
            'SPDI', 'SPD', 'MPD', 'SSI', 'SAG', 'MAG', 'MA',
            'MSI', 'MSC', 'MHUM', 'SE', 'SH', 'SKM', 'PH', 'D', 'PHD',
        ];

        $parts = array_values(array_filter(
            preg_split('/\s+/', trim($normalized)) ?: [],
            static fn (string $part): bool => $part !== '' && ! in_array($part, $ignoredParts, true)
        ));

        $base = $parts !== [] ? (string) end($parts) : 'XXX';
        $mark = substr(preg_replace('/[^A-Z0-9]/', '', $base) ?: 'XXX', 0, 3);

        return str_pad($mark, 3, 'X');
    }

    private function titleMark(string $title): string
    {
        $normalized = $this->normalize($title);
        $words = preg_split('/\s+/', $normalized) ?: [];
        $ignoredOpeners = ['a', 'an', 'the', 'sebuah', 'sang', 'si'];

        foreach ($words as $word) {
            if ($word === '' || in_array($word, $ignoredOpeners, true)) {
                continue;
            }

            return substr($word, 0, 1);
        }

        return 'x';
    }

    private function normalize(string $value): string
    {
        $value = strtolower(Str::ascii($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?: '';

        return trim(preg_replace('/\s+/', ' ', $value) ?: '');
    }

    private function containsPhrase(string $searchText, string $phrase): bool
    {
        if ($searchText === '' || $phrase === '') {
            return false;
        }

        return str_contains(' '.$searchText.' ', ' '.$phrase.' ');
    }
}
