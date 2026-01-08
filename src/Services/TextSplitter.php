<?php

namespace Surabayacoder\Sage\Services;

class TextSplitter
{
    /**
     * Memecah teks menjadi potongan-potongan (chunks) yang lebih kecil
     * menggunakan strategi "Recursive Character Text Splitter".
     *
     * @param string $text Teks asli
     * @param int $chunkSize Ukuran maksimal per chunk (karakter)
     * @param int $chunkOverlap Overlap antar chunk (opsional)
     * @return array Daftar chunk
     */
    public static function split(string $text, int $chunkSize = 1000, int $chunkOverlap = 200): array
    {
        $separators = ["\n\n", "\n", " ", ""];

        return self::splitText($text, $separators, $chunkSize, $chunkOverlap);
    }

    private static function splitText(string $text, array $separators, int $chunkSize, int $chunkOverlap): array
    {
        $finalChunks = [];
        $separator = array_shift($separators);

        // Jika tidak ada separator tersisa, potong paksa
        if ($separator === null) {
             return self::cutText($text, $chunkSize);
        }

        $splits = explode($separator, $text);
        $currentChunk = [];
        $currentLength = 0;

        foreach ($splits as $split) {
            $splitLen = strlen($split);

            // Jika satu bagian ini saja sudah lebih besar dari chunkSize,
            // kita harus memecahnya lagi secara rekursif dengan separator berikutnya
            if ($splitLen > $chunkSize) {
                // Jika chunk yang sedang dibangun ada isinya, simpan dulu
                if (!empty($currentChunk)) {
                    $doc = implode($separator, $currentChunk);
                    $finalChunks[] = $doc;
                    $currentChunk = [];
                    $currentLength = 0;
                }

                // Rekursif untuk split yang kegedean ini
                $subChunks = self::splitText($split, $separators, $chunkSize, $chunkOverlap);
                $finalChunks = array_merge($finalChunks, $subChunks);
                continue;
            }

            // Cek apakah kalau ditambah split ini masih muat?
            // Kita +1 untuk panjang separator (perkiraan)
            if ($currentLength + $splitLen + strlen($separator) > $chunkSize) {
                 // Sudah penuh, simpan chunk saat ini
                 $doc = implode($separator, $currentChunk);
                 $finalChunks[] = $doc;

                 // Mulai chunk baru.
                 // Nah, di sini logika Overlap bisa masuk.
                 // Untuk kesederhanaan, kita reset dulu, tapi idealnya kita keep beberapa kalimat terakhir (overlap).
                 // Implementasi overlap sederhana:
                 $overlapSplit = [];
                 if ($chunkOverlap > 0 && !empty($currentChunk)) {
                      // Ambil beberapa item terakhir dari chunk sebelumnya agar total panjangnya kira-kira <= chunkOverlap
                      $overlapLen = 0;
                      $reversedChunk = array_reverse($currentChunk);
                      foreach ($reversedChunk as $item) {
                          if ($overlapLen + strlen($item) > $chunkOverlap) break;
                          $overlapSplit[] = $item;
                          $overlapLen += strlen($item);
                      }
                      $overlapSplit = array_reverse($overlapSplit);
                 }

                 $currentChunk = array_merge($overlapSplit, [$split]);

                 // Recalculate length
                 $currentLength = 0;
                 foreach($currentChunk as $c) {
                     $currentLength += strlen($c) + strlen($separator);
                 }

            } else {
                $currentChunk[] = $split;
                $currentLength += $splitLen + strlen($separator);
            }
        }

        if (!empty($currentChunk)) {
            $finalChunks[] = implode($separator, $currentChunk);
        }

        return $finalChunks;
    }

    private static function cutText(string $text, int $chunkSize): array
    {
        if ($text === '') return [];
        return str_split($text, $chunkSize);
    }
}
