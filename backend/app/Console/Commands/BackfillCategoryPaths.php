<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BackfillCategoryPaths extends Command
{
    protected $signature = 'catalog:categories {file : JSON de uma coleta anterior}';

    protected $description = 'Completa categorias ausentes de produtos importados sem alterar estoque ou edições';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Disponível somente em local/testing.');

            return self::FAILURE;
        }
        $file = $this->argument('file');
        if (! is_file($file) || filesize($file) > 10 * 1024 * 1024) {
            $this->error('Arquivo ausente ou maior que 10 MiB.');

            return self::FAILURE;
        }
        $rows = json_decode(file_get_contents($file), true);
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->error('Amostra inválida.');

            return self::FAILURE;
        }
        $changed = DB::transaction(function () use ($rows) {
            $count = 0;
            foreach ($rows as $row) {
                if (! is_array($row) || Validator::make($row, [
                    'source_url' => ['required', 'url:https', 'max:2048'],
                    'category_path' => ['required', 'array', 'min:1', 'max:30'],
                    'category_path.*' => ['required', 'string', 'max:100'],
                ])->fails()) {
                    continue;
                }
                $product = Product::where('import_key', hash('sha256', $row['source_url']))
                    ->where('source_url', $row['source_url'])->whereNull('category_path')->lockForUpdate()->first();
                if ($product) {
                    $product->timestamps = false;
                    $product->forceFill(['category_path' => array_values(array_unique($row['category_path']))])->save();
                    $count++;
                }
            }

            return $count;
        });
        $this->info($changed.' produtos complementados; demais campos preservados.');

        return self::SUCCESS;
    }
}
