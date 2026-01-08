<?php

namespace Surabayacoder\Sage\Support;

class VectorHelper
{
    /**
     * Menghitung Cosine Similarity antara dua vektor.
     *
     * @param array $vecA Vektor pertama
     * @param array $vecB Vektor kedua
     * @return float Nilai kemiripan (0 hingga 1, atau -1 hingga 1)
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0;
        $magA = 0;
        $magB = 0;
        $count = count($vecA);

        if ($count === 0 || $count !== count($vecB)) {
            return 0;
        }

        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magA += $vecA[$i] * $vecA[$i];
            $magB += $vecB[$i] * $vecB[$i];
        }

        $magA = sqrt($magA);
        $magB = sqrt($magB);

        if ($magA == 0 || $magB == 0) {
            return 0;
        }

        return $dotProduct / ($magA * $magB);
    }
}
