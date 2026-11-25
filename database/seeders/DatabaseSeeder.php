<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ComparisonSource;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Optional demonstration source. Replace with licensed/approved content.
        ComparisonSource::firstOrCreate(
            ['title' => 'Sample institutional source — demonstration only'],
            [
                'source_type' => 'institutional',
                'content' => 'This is a demonstration source text for testing the originality checker. Similarity checking compares sequences of words against approved source documents stored in the local comparison library. This sample content is not an academic publication and must be replaced before real use.',
                'is_active' => true,
            ]
        );
    }
}
