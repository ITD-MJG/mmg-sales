<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Builder;

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
                                        $set('sales', []);
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
                                            ->where(fn (Builder $query) => $query->where('created_by', auth()->id())->orWhere('assigned_to', auth()->id()))
                                            ->pluck('title', 'id'),
                                        'opportunities' => Opportunity::query()
                                            ->where(fn (Builder $query) => $query->where('created_by', auth()->id())->orWhere('assigned_to', auth()->id()))
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
                                            'leads' => $state ? Lead::query()->where(fn (Builder $query) => $query->where('created_by', auth()->id())->orWhere('assigned_to', auth()->id()))->with(['customer', 'products.principal', 'creator', 'collaborators'])->find($state) : null,
                                            'opportunities' => $state ? Opportunity::query()->where(fn (Builder $query) => $query->where('created_by', auth()->id())->orWhere('assigned_to', auth()->id()))->with(['customer', 'products.principal', 'creator', 'collaborators'])->find($state) : null,
                                            default => null,
                                        };

                                        if (! $source) {
                                            $set('end_customer_id', null);
                                            $set('notes', null);
                                            $set('sales', []);
                                            $set('orderItems', []);
                                            $set('subtotal', 0);
                                            $set('total_amount', 0);
                                            $set('net_sales_total', 0);

                                            return;
                                        }

                                        $set('end_customer_id', $source->customer_id);
                                        $set('notes', $source->notes);
                                        $salesIds = collect([$source->creator?->id])
                                            ->merge($source->collaborators->pluck('id'))
                                            ->filter()
                                            ->unique()
                                            ->values()
                                            ->all();
                                        $set('sales', $salesIds);

                                        $items = $source->products->map(function ($product) use ($sourceType): array {
                                            $quantity = $sourceType === 'leads' ? ($product->pivot->quantity ?? 1) : 1;
                                            $unitPrice = $sourceType === 'leads'
                                                ? ($product->pivot->unit_price ?? $product->unit_price ?? 0)
                                                : ($product->unit_price ?? 0);
                                            $principalId = $product->pivot->principal_id ?? $product->principal_id;

                                            return [
                                                'principal_id' => $principalId,
                                                'item_id' => $product->id,
                                                'quantity' => $quantity,
                                                'price_type' => 'unit_price',
                                                'unit_price' => $unitPrice,
                                                'current_price' => $unitPrice,
                                                'subtotal' => $unitPrice * $quantity,
                                            ];
                                        })->toArray();

                                        $set('orderItems', $items);
                                    }),
                                DatePicker::make('order_date')
                                    ->label('Order Date')
                                    ->default(now())
                                    ->required(),
                            ]),

                        Grid::make(3)
                            ->schema([
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
                        Select::make('sales')
                            ->label('Sales / Marketing')
                            ->multiple()
                            ->options(fn () => User::query()
                                ->whereHas('department', fn (Builder $query) => $query->whereIn('code', ['SAL', 'MKT']))
                                ->pluck('name', 'id'))
                            ->default(fn () => $user?->id ? [$user->id] : [])
                            ->preload()
                            ->afterStateUpdated(function ($set, $get, $state): void {
                                if (! $state || $get('source_record_id')) {
                                    return;
                                }

                                $allowedIds = User::query()
                                    ->whereHas('department', fn (Builder $query) => $query->whereIn('code', ['SAL', 'MKT']))
                                    ->pluck('id');

                                $set('sales', array_values(array_intersect($state, $allowedIds->all())));
                            })
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
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('orderItems')
                            ->relationship()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        Select::make('principal_id')
                                            ->label('Principal')
                                            ->options(fn () => Principal::query()->where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set) => $set('item_id', null)),
                                        Select::make('item_id')
                                            ->label('Product')
                                            ->options(fn ($get) => $get('principal_id')
                                                ? Product::where('principal_id', $get('principal_id'))->pluck('name', 'id')
                                                : [])
                                            ->searchable()
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateLineTotal($set, $get)),
                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateLineTotal($set, $get))
                                            ->columnSpan(1),
                                        Select::make('price_type')
                                            ->label('Price Type')
                                            ->options([
                                                'unit_price' => 'Unit Price',
                                                'ecatalog_price' => 'E-Catalog Price',
                                            ])
                                            ->default('unit_price')
                                            ->live()
                                            ->afterStateUpdated(fn ($set, $get) => self::updateLineTotal($set, $get, true))
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->mask(RawJs::make('$money($input, ".", ",", 2)'))
                                            ->stripCharacters(['.', ','])
                                            ->prefix('IDR')
                                            ->live()
                                            ->readOnly(fn ($get) => ! $get('principal_id') || ! $get('item_id'))
                                            ->afterStateUpdated(fn ($set, $get) => self::updateLineTotal($set, $get))
                                            ->columnSpan(1),
                                    ]),
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->label('Line Total')
                                            ->mask(RawJs::make('$money($input, ".", ",", 2)'))
                                            ->stripCharacters(['.', ','])
                                            ->prefix('IDR')
                                            ->readOnly()
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columns(1)
                            ->live()
                            ->afterStateUpdated(fn ($set, $get) => self::calculateOrderTotals($set, $get)),
                    ]),

                Section::make('Total')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Gross Sales')
                                    ->mask(RawJs::make('$money($input, ".", ",", 0)'))
                                    ->step('1')
                                    ->stripCharacters(['.', ','])
                                    ->prefix('IDR')
                                    ->readOnly(),
                                TextInput::make('discount_on')
                                    ->label('Discount (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->live()
                                    ->afterStateUpdated(fn ($set, $get) => self::calculateNetSales($set, $get)),
                                TextInput::make('total_amount')
                                    ->label('Net Sales')
                                    ->step('1')
                                    ->mask(RawJs::make('$money($input, ".", ",", 0)'))
                                    ->stripCharacters(['.', ','])
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

    private static function parseMoney(mixed $value): float
    {
        if (! is_string($value)) {
            return (float) $value;
        }

        $parts = explode(',', $value, 2);
        $integerPart = (float) str_replace('.', '', $parts[0]);
        $fractionalPart = isset($parts[1]) ? (float) ('0.'.preg_replace('/\D/', '', $parts[1])) : 0;

        return $integerPart + $fractionalPart;
    }

    protected static function updateLineTotal($set, $get, bool $refreshPrice = false): void
    {
        $itemId = $get('item_id');
        $quantity = (int) $get('quantity');
        $priceType = $get('price_type') ?? 'unit_price';

        if ($refreshPrice && $itemId) {
            $product = Product::find($itemId);
            if ($product) {
                $unitPrice = $priceType === 'ecatalog_price'
                    ? ($product->ecatalog_price ?? $product->unit_price ?? 0)
                    : ($product->unit_price ?? 0);
                $set('unit_price', $unitPrice);
                $set('current_price', $unitPrice);
            }
        }

        $unitPrice = self::parseMoney($get('unit_price'));
        $lineTotal = round($quantity * $unitPrice, 2);
        $set('subtotal', $lineTotal);
    }

    protected static function calculateOrderTotals($set, $get): void
    {
        $orderItems = $get('orderItems');
        $grossSales = 0;

        if ($orderItems && is_array($orderItems)) {
            foreach ($orderItems as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $unitPrice = self::parseMoney($item['unit_price'] ?? 0);
                $grossSales += $quantity * $unitPrice;
            }
        }

        $set('subtotal', round($grossSales));

        $discountPercent = (float) $get('discount_on');
        $netSales = round($grossSales * (1 - ($discountPercent / 100)));
        $set('total_amount', $netSales);
        $set('net_sales_total', $netSales);
    }

    protected static function calculateNetSales($set, $get): void
    {
        $grossSales = (float) $get('subtotal');
        $discountPercent = (float) $get('discount_on');
        $netSales = round($grossSales * (1 - ($discountPercent / 100)));
        $set('total_amount', $netSales);
        $set('net_sales_total', $netSales);
    }
}
