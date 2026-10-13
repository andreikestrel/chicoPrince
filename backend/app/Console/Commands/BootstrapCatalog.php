<?php

namespace App\Console\Commands;

use App\Models\Product;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ProductDemoVariantsSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class BootstrapCatalog extends Command
{
    protected $signature = 'catalog:bootstrap {--limit=20 : Meta de 1 a 45 produtos} {--offline : Usar apenas produtos fictícios} {--file= : JSON coletado anteriormente} {--replace-demo : Substituir somente fictícios intactos após coleta válida} {--dry-run : Mostrar plano sem alterar produtos}';

    protected $description = 'Inicializa catálogo vazio com coleta pública ou fallback fictício, preservando dados existentes';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Comando disponível somente em local/testing.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 45]]);
        if ($limit === false || ($this->option('offline') && $this->option('file'))) {
            $this->error('Use limite de 1 a 45; --offline e --file são exclusivos.');

            return self::FAILURE;
        }
        // Evita duas inicializações simultâneas no mesmo ambiente.
        $lock = Cache::lock('catalog-bootstrap', 2300);
        if (! $lock->get()) {
            $this->error('Uma inicialização já está em andamento.');

            return self::FAILURE;
        }
        try {
            if (Product::exists() && ! $this->option('replace-demo')) {
                $this->info('Catálogo existente preservado. Nenhuma coleta ou alteração executada.');

                return self::SUCCESS;
            }
            // Confere credenciais antes de gastar tempo consultando a origem.
            if (! $this->option('dry-run')) {
                $this->call('db:seed', ['--class' => DemoUserSeeder::class, '--force' => true]);
                $this->call('db:seed', ['--class' => DemoAccountsSeeder::class, '--force' => true]);
            }
            $rows = [];
            if (! $this->option('offline')) {
                try {
                    $file = $this->option('file');
                    if (! $file) {
                        $file = storage_path('app/catalog-bootstrap.json');
                        if (is_file($file)) {
                            unlink($file);
                        }
                        $result = Process::timeout(2200)->run([
                            'python3', '/opt/chicorei/collect.py', '--limit', (string) $limit, '--output', $file,
                        ]);
                        if (! $result->successful()) {
                            throw new \RuntimeException('Coletor indisponível.');
                        }
                    }
                    if (! is_file($file) || filesize($file) > 10 * 1024 * 1024) {
                        throw new \RuntimeException('Arquivo ausente ou maior que 10 MiB.');
                    }
                    $data = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                    if (! is_array($data) || ! array_is_list($data)) {
                        throw new \RuntimeException('Formato de amostra inválido.');
                    }
                    $seen = [];
                    foreach (array_slice($data, 0, 45) as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $validator = Validator::make($item, [
                            'source' => ['required', 'in:chicorei'],
                            'source_url' => ['required', 'url:https', 'max:2048'],
                            'name' => ['required', 'string', 'max:255'],
                            'description' => ['required', 'string', 'max:20000'],
                            'price' => ['required', 'regex:/^\d{1,8}\.\d{2}$/'],
                            'currency' => ['required', 'in:BRL'],
                            'image_url' => ['required', 'url:https', 'max:2048'],
                            'category_path' => ['required', 'array', 'min:1'],
                            'category_path.*' => ['required', 'string', 'max:100'],
                        ]);
                        if ($validator->fails() || parse_url($item['source_url'], PHP_URL_HOST) !== 'chicorei.com') {
                            continue;
                        }
                        $key = $item['source_url'];
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;
                        $categories = array_values(array_filter($item['category_path'], fn (string $name) => ! in_array(mb_strtolower($name), ['roupas', 'acessórios', 'casa'], true)));
                        $rows[] = [
                            'name' => $item['name'], 'description' => $item['description'],
                            'price' => $item['price'], 'image' => $item['image_url'],
                            'category' => ($categories[0] ?? $item['category_path'][0]), 'stock' => 0,
                            'import_key' => hash('sha256', $key), 'source_url' => $key,
                        ];
                        if (count($rows) === $limit) {
                            break;
                        }
                    }
                } catch (Throwable) {
                    $this->warn('Coleta/arquivo indisponível. Usando fallback fictício.');
                }
            }
            if ($this->option('replace-demo') && ! $rows) {
                $this->warn('Sem amostra válida: catálogo existente preservado, sem remoções.');

                return self::SUCCESS;
            }
            $candidates = $this->option('replace-demo')
                ? Product::all()->filter(fn (Product $product) => ProductSeeder::pristine($product))->pluck('id')->all()
                : [];
            $this->info('Amostra válida: '.count($rows).'; fictícios intactos candidatos: '.count($candidates).'.');
            if ($this->option('dry-run')) {
                $this->line('IDs candidatos: '.implode(', ', $candidates));

                return self::SUCCESS;
            }
            $created = 0;
            $removed = 0;
            DB::transaction(function () use ($rows, &$created, &$removed): void {
                $current = Product::query()->lockForUpdate()->get();
                if ($current->isNotEmpty() && ! $this->option('replace-demo')) {
                    throw new \RuntimeException('Catálogo recebeu produtos durante a coleta; inicialização cancelada.');
                }
                if ($rows) {
                    $ids = $this->option('replace-demo')
                        ? $current->filter(fn (Product $product) => ProductSeeder::pristine($product))->pluck('id')->all()
                        : [];
                    if ($ids) {
                        $backup = 'catalog-backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
                        $snapshot = $current->load('variants')->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        if (! Storage::disk('local')->put($backup, $snapshot)) {
                            throw new \RuntimeException('Backup falhou; nenhuma remoção será realizada.');
                        }
                        $this->info('Backup local: '.Storage::disk('local')->path($backup));
                    }
                    foreach ($rows as $row) {
                        if (! Product::where('import_key', $row['import_key'])->exists()) {
                            $product = new Product;
                            $product->forceFill($row)->save();
                            $created++;
                        }
                    }
                    if ($ids) {
                        $removed = Product::whereIn('id', $ids)->delete();
                    }
                    $this->call('db:seed', ['--class' => ProductDemoVariantsSeeder::class, '--force' => true]);
                } else {
                    $this->call('db:seed', ['--class' => ProductSeeder::class, '--force' => true]);
                }
            });
            $this->info($rows
                ? $created.' importados; '.$removed.' fictícios intactos removidos. Demais produtos preservados.'
                : 'Catálogo fictício inicializado, sem depender de rede.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error('Inicialização não concluída: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
