<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'sales_id')) {
                $table->unsignedBigInteger('sales_id')->nullable();
            }
        });

        if (Schema::hasColumn('orders', 'sales')) {
            DB::table('orders')->select(['id', 'sales'])->orderBy('id')->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $sales = json_decode((string) $order->sales, true);

                    if (! is_array($sales)) {
                        $sales = filled($order->sales) ? [$order->sales] : [];
                    }

                    $salesId = collect($sales)->first(fn ($id) => is_numeric($id));

                    if ($salesId !== null) {
                        DB::table('orders')->where('id', $order->id)->update(['sales_id' => (int) $salesId]);
                    }
                }
            });
        }

        $this->dropSalesForeignKey();

        Schema::table('orders', function (Blueprint $table): void {
            $columnsToDrop = ['head_position_id', 'pm_jpm_pe_position_id', 'rsm_asm_position_id', 'spv_position_id', 'sales'];
            foreach (['department_id', 'head_position_id', 'pm_jpm_pe_position_id', 'rsm_asm_position_id', 'spv_position_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (Throwable $e) {
                        // The column may not have a foreign key constraint.
                    }
                }
            }

            $columnsToDrop = array_values(array_filter($columnsToDrop, fn ($column) => Schema::hasColumn('orders', $column)));
            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
            $table->foreignId('department_id')->nullable()->change();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreign('sales_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function dropSalesForeignKey(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('orders', function (Blueprint $table): void {
                try {
                    $table->dropForeign(['sales']);
                } catch (Throwable $e) {
                    // The legacy sales column may not have a foreign key.
                }

                try {
                    $table->dropForeign('orders_sr_position_id_foreign');
                } catch (Throwable $e) {
                    // Legacy constraint name may vary or be absent.
                }
            });

            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'orders')
            ->where('COLUMN_NAME', 'sales')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `orders` DROP FOREIGN KEY `{$constraint}`");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'department_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropForeign(['department_id']);
            });
        }

        foreach (['head_position_id', 'pm_jpm_pe_position_id', 'rsm_asm_position_id', 'spv_position_id'] as $column) {
            if (! Schema::hasColumn('orders', $column)) {
                Schema::table('orders', function (Blueprint $table) use ($column): void {
                    $table->foreignId($column)->nullable()->constrained('positions')->nullOnDelete();
                });
            }
        }

        if (Schema::hasColumn('orders', 'sales_id') && ! Schema::hasColumn('orders', 'sales')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->json('sales')->nullable();
            });
            DB::table('orders')->whereNotNull('sales_id')->orderBy('id')->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)->update(['sales' => json_encode([(int) $order->sales_id])]);
                }
            });
        }

        if (Schema::hasColumn('orders', 'sales_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropForeign(['sales_id']);
                $table->dropColumn('sales_id');
            });
        }

        if (Schema::hasColumn('orders', 'department_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('department_id')->nullable(false)->change();
                $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            });
        }
    }

};
