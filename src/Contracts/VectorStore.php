<?php

namespace Surabayacoder\Sage\Contracts;

interface VectorStore
{
    /**
     * Menyimpan array dari vektor ke dalam database.
     * @param array $vectors
     * @return void
     */
    public function save(array $vectors): void;

    /**
     * Mencari vektor yang paling mirip dari database.
     * @param array $queryVector
     * @param int $limit
     * @return array
     */
    public function search(array $queryVector, int $limit = 3): array;
}
