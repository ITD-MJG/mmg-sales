<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Exports\OrderItemsExport;
use App\Imports\OrderItemsImport;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();

        return $schema
            ->components([
                Section::make('Order Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('order_source')
                                    ->label('Order Source')
                                    ->options([
                                        'leads' => 'Leads',
                                        'opportunities' => 'Opportunities',
                                        'manual' => 'Input Manually',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($set): void {
                                        $set('source_record_id', null);
                                        $set('end_customer_id', null);
                                        $set('notes', null);
                                        $set('sales_id', null);
                                        $set('orderItems', []);
                                        $set('subtotal', 0);
                                        $set('total_amount', 0);
                                        $set('net_sales_total', 0);
                                    })
                                    ->default('manual')
                                    ->dehydrated(false),
                                Select::make('source_record_id')
                                    ->label('Lead / Opportunity')
                                    ->options(fn ($get) => match ($get('order_source')) {
                                        'leads' => Lead::query()
                                            ->where('created_by', auth()->id())
                                            ->pluck('title', 'id'),
                                        'opportunities' => Opportunity::query()
                                            ->where('created_by', auth()->id())
                                            ->pluck('title', 'id'),
                                        default => [],
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select a lead or opportunity')
                                    ->live()
                                    ->dehydrated(false)
                                    ->visible(fn ($get) => in_array($get('order_source'), ['leads', 'opportunities'], true))
                                    ->required(false)
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function ($set, $get, ?string $state): void {
                                        $sourceType = $get('order_source');
                                        $source = match ($sourceType) {
                                            'leads' => $state ? Lead::query()->where('created_by', auth()->id())->with(['customer', 'products.principal', 'creator', 'collaborators'])->find($state) : null,
                                            'opportunities' => $state ? Opportunity::query()->where('created_by', auth()->id())->with(['customer', 'products.principal', 'creator', 'collaborators'])->find($state) : null,
                                            default => null,
                                        };

                                        if (! $source) {
                                            $set('end_customer_id', null);
                                            $set('notes', null);
                                            $set('sales_id', null);
                                            $set('orderItems', []);
                                            $set('subtotal', 0);
                                            $set('total_amount', 0);
                                            $set('net_sales_total', 0);

                                            return;
                                        }

                                        $set('end_customer_id', $source->customer_id);
                                        $set('notes', $source->notes);
                                        $salesId = $source->creator?->id ?? $source->collaborators->first()?->id;
                                        $set('sales_id', $salesId);

                                        $items = $source->products->map(function ($product) use ($sourceType): array {
                                            $quantity = $sourceType === 'leads' ? ($product->pivot->quantity ?? 1) : 1;
                                            $unitPrice = $sourceType === 'leads'
                                                ? ($product->pivot->unit_price ?? $product->unit_price ?? 0)
                                                : ($product->unit_price ?? 0);
                                            $principalId = $product->pivot->principal_id ?? $product->principal_id;

                                            return [
                                                'principal_id' => $principalId,
                                                'principal_name' => $product->principal?->name,
                                                'item_id' => $product->id,
                                                'quantity' => $quantity,
                                                'price_type' => 'unit_price',
                                                'unit_price' => (float) $unitPrice,
                                                'current_price' => (float) $unitPrice,
                                                'discount_amount' => 0,
                                                'discount_percent' => 0,
                                                'subtotal' => (float) $unitPrice * $quantity,
                                            ];
                                        })->toArray();

                                        $set('orderItems', $items);
                                    }),
                                DatePicker::make('order_date')
                                    ->label('Order Date')
                                    ->default(now())
                                    ->required(),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('po_number')
                                    ->label('PO Number')
                                    ->placeholder('Enter PO number'),
                                TextInput::make('payment_method')
                                    ->label('Payment Method')
                                    ->placeholder('e.g. Bank Transfer, Credit Card'),
                                Select::make('reg_inst')
                                    ->label('Type')
                                    ->multiple()
                                    ->options([
                                        'REG' => 'Reagent',
                                        'INST' => 'Instrument',
                                        'Consumable' => 'Consumable',
                                    ])
                                    ->searchable(),
                                Select::make('sales_type_id')
                                    ->label('Purchase Type')
                                    ->options([
                                        'INAPROC' => 'INAPROC',
                                        'non-INAPROC' => 'non-INAPROC',
                                    ])
                                    ->searchable(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('jual_kso')
                                    ->label('Jual / KSO')
                                    ->options([
                                        'Jual' => 'Jual',
                                        'KSO' => 'KSO',
                                        'Sample' => 'Sample',
                                    ])
                                    ->required()
                                    ->searchable(),
                                Select::make('distributor_id')
                                    ->label('Distributor')
                                    ->relationship('distributor', 'name')
                                    ->required()
                                    ->preload()
                                    ->searchable(),
                            ]),

                        TextInput::make('tahun')
                            ->default(now()->year)
                            ->hidden(),
                        TextInput::make('bulan')
                            ->default(now()->month)
                            ->hidden(),
                    ]),

                Section::make('Sales Details')
                    ->schema([
                        Select::make('sales_id')
                            ->label('Sales / Marketing')
                            ->options(fn () => User::query()
                                ->when(! in_array($user?->department?->code, ['SAL', 'MKT'], true), fn (Builder $query) => $query->whereHas('department', fn (Builder $departmentQuery) => $departmentQuery->where('code', 'SAL')))
                                ->whereHas('department', fn (Builder $query) => $query->whereIn('code', ['SAL', 'MKT']))
                                ->pluck('name', 'id'))
                            ->default(fn () => in_array($user?->department?->code, ['SAL', 'MKT'], true) ? $user->id : null)
                            ->preload()
                            ->searchable(),
                    ]),

                Section::make('Customer Details')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('end_customer_id')
                            ->label('End Customer')
                            ->relationship(
                                name: 'customer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->required()
                            ->preload()
                            ->live()
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($set, $state) {
                                if (! $state) {
                                    $set('billing_address', null);

                                    return;
                                }

                                $customer = Customer::find($state);
                                if (! $customer) {
                                    return;
                                }

                                $set('billing_address', $customer->address);
                            }),

                        Grid::make(2)
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Textarea::make('billing_address')
                                            ->label('Billing Address')
                                            ->rows(3)
                                            ->live()
                                            ->afterStateUpdated(function ($get, $set, $state) {
                                                if ($get('shipping_same_as_billing')) {
                                                    $set('shipping_address', $state);
                                                }
                                            }),
                                        Checkbox::make('shipping_same_as_billing')
                                            ->label('Shipping address same as billing')
                                            ->default(true)
                                            ->live()
                                            ->afterStateUpdated(function ($get, $set, $state) {
                                                if ($state) {
                                                    $set('shipping_address', $get('billing_address'));
                                                }
                                            }),
                                    ])->columnSpan(1),

                                Textarea::make('shipping_address')
                                    ->label('Shipping Address')
                                    ->rows(3)
                                    ->disabled(fn ($get) => $get('shipping_same_as_billing'))
                                    ->dehydrated()
                                    ->columnSpan(1),
                            ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Product Details')
                    ->headerActions([
                        Action::make('exportOrderItems')
                            ->label('Export to Excel')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->action(function ($livewire, $get) {
                                $items = collect($get('orderItems') ?? []);
                                $products = Product::query()->whereIn('id', $items->pluck('item_id')->filter())->pluck('name', 'id');
                                $principals = Principal::query()->whereIn('id', $items->pluck('principal_id')->filter())->pluck('name', 'id');
                                $items = $items->map(function (array $item) use ($products, $principals): array {
                                    return [...$item,
                                        'item_name' => $products[$item['item_id'] ?? null] ?? null,
                                        'principal_name' => $principals[$item['principal_id'] ?? null] ?? null,
                                    ];
                                })->all();

                                return Excel::download(
                                    new OrderItemsExport($items),
                                    'order-products.xlsx',
                                );
                            }),
                        Action::make('importOrderItems')
                            ->label('Import Products')
                            ->icon('heroicon-o-arrow-up-tray')
                            ->form([
                                FileUpload::make('file')
                                    ->label('Excel File')
                                    ->disk('local')
                                    ->directory('imports')
                                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                                    ->required(),
                            ])
                            ->action(function (array $data, $get, $set): void {
                                $path = Storage::disk('local')->path($data['file']);
                                $import = new OrderItemsImport;
                                Excel::import($import, $path);

                                $set('orderItems', [...($get('orderItems') ?? []), ...$import->items()]);
                                self::calculateOrderTotals($set, (float) $get('discount_on'), $get('orderItems') ?? []);

                                if ($import->failures() !== []) {
                                    Notification::make()
                                        ->warning()
                                        ->title('Some rows were not imported')
                                        ->body(collect($import->failures())->map(fn ($failure) => 'Row '.$failure['row'].': '.$failure['reason'])->implode(' '))
                                        ->send();
                                }
                            }),
                    ])
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('orderItems')
                            ->relationship()
                            ->schema([
                                Grid::make(7)
                                    ->schema([
                                        Select::make('item_id')
                                            ->label('Product')
                                            ->options(fn () => Product::query()
                                                ->orderBy('name')
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, $set, $get): void {
                                                $product = $state ? Product::query()->with('principal')->find($state) : null;
                                                $set('principal_id', $product?->principal_id);
                                                $set('principal_name', $product?->principal?->name);
                                                self::updateLineTotal($set, $get, true);
                                                self::calculateOrderTotals($set, (float) $get('../../discount_on'), $get('../') ?? []);
                                            }),
                                        TextInput::make('principal_name')
                                            ->label('Principal')
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(function (TextInput $component, $get): void {
                                                $product = $get('item_id') ? Product::query()->with('principal')->find($get('item_id')) : null;
                                                $component->state($product?->principal?->name);
                                            }),
                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get): void {
                                                self::updateLineTotal($set, $get);
                                                self::calculateOrderTotals($set, (float) $get('../../discount_on'), $get('../') ?? []);
                                            })
                                            ->columnSpan(1),
                                        Select::make('price_type')
                                            ->label('Price Type')
                                            ->options([
                                                'unit_price' => 'Unit Price',
                                                'ecatalog_price' => 'E-Catalog Price',
                                            ])
                                            ->default('unit_price')
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get): void {
                                                self::updateLineTotal($set, $get, true);
                                                self::calculateOrderTotals($set, (float) $get('../../discount_on'), $get('../') ?? []);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix('IDR')
                                            ->live()
                                            ->afterStateUpdated(function ($set, $get): void {
                                                self::updateLineTotal($set, $get);
                                                self::calculateOrderTotals($set, (float) $get('../../discount_on'), $get('../') ?? []);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('discount_percent')
                                            ->label('Discount')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->dehydrated(false)
                                            ->live()
                                            ->afterStateHydrated(function (TextInput $component, $get): void {
                                                $quantity = (int) ($get('quantity') ?? 0);
                                                $gross = $quantity * (float) $get('unit_price');
                                                $discountAmount = (float) $get('discount_amount');
                                                $component->state($gross > 0 ? round(($discountAmount / $gross) * 100, 4) : 0);
                                            })
                                            ->afterStateUpdated(function ($set, $get): void {
                                                self::updateLineTotal($set, $get);
                                                self::calculateOrderTotals($set, (float) $get('../../discount_on'), $get('../') ?? []);
                                            })
                                            ->columnSpan(1),
                                        TextInput::make('subtotal')
                                            ->label('Line Total')
                                            ->numeric()
                                            ->prefix('IDR')
                                            ->readOnly()
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columns(1)
                            ->live()
                            ->afterStateUpdated(fn ($set, $get, $state) => self::calculateOrderTotals($set, (float) $get('discount_on'), $state ?? [])),
                    ]),

                Section::make('Total')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Gross Sales')
                                    ->step('1')
                                    ->prefix('IDR')
                                    ->readOnly(),
                                TextInput::make('discount_on')
                                    ->label('Discount (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::calculateNetSales($set, (float) $get('subtotal'), (float) $get('discount_on'))),
                                TextInput::make('total_amount')
                                    ->label('Net Sales')
                                    ->step('1')
                                    ->prefix('IDR')
                                    ->readOnly(),
                            ]),
                    ]),

                Select::make('created_by')
                    ->relationship('creator', 'name')
                    ->default(auth()->id())
                    ->hidden()
                    ->preload(),
            ]);
    }

    protected static function updateLineTotal($set, $get, bool $refreshPrice = false): void
    {
        $itemId = $get('item_id');
        $quantity = max(0, (int) $get('quantity'));
        $priceType = $get('price_type') ?? 'unit_price';
        $product = $itemId ? Product::query()->with('principal')->find($itemId) : null;

        if ($product) {
            $set('principal_id', $product->principal_id);
            $set('principal_name', $product->principal?->name);
        } else {
            $set('principal_id', null);
            $set('principal_name', null);
        }

        if ($refreshPrice && $product) {
            $unitPrice = $priceType === 'ecatalog_price'
                ? ($product->ecatalog_price ?? $product->unit_price ?? 0)
                : ($product->unit_price ?? 0);
            $set('unit_price', $unitPrice);
            $set('current_price', $unitPrice);
        }

        $unitPrice = (float) $get('unit_price');
        $grossTotal = $quantity * $unitPrice;
        $discountPercent = min(100, max(0, (float) $get('discount_percent')));
        $discountAmount = round($grossTotal * ($discountPercent / 100), 2);

        $set('discount_amount', $discountAmount);
        $set('subtotal', round($grossTotal - $discountAmount, 2));
    }

    protected static function calculateOrderTotals($set, float $discountPercent, array $orderItems): void
    {
        $lineTotal = 0.0;

        foreach ($orderItems as $item) {
            $lineTotal += (float) ($item['subtotal'] ?? 0);
        }

        $set('subtotal', round($lineTotal, 2));
        self::calculateNetSales($set, round($lineTotal, 2), $discountPercent);
    }

    protected static function calculateNetSales($set, float $grossSales, float $discountPercent): void
    {
        $netSales = round($grossSales * (1 - ($discountPercent / 100)));
        $set('total_amount', $netSales);
        $set('net_sales_total', $netSales);
    }
}
