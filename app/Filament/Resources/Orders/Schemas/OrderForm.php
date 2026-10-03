<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Opportunity;
use App\Models\Position;
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
use Illuminate\Database\Eloquent\Builder;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();
        $userPosition = $user?->position;

        $findAncestorByLevel = function (?Position $position, int $targetLevel): ?int {
            if (! $position) {
                return null;
            }

            $current = $position;
            while ($current && $current->level > $targetLevel) {
                $current = $current->parent;
            }

            return $current?->id;
        };

        return $schema
            ->components([
                Section::make('Order Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('order_source')
                                    ->label('Order Source')
                                    ->options([
                                        'opportunities' => 'Opportunities',
                                        'manual' => 'Input Manually',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('manual')
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                DatePicker::make('order_date')
                                    ->label('Order Date')
                                    ->default(now())
                                    ->required()
                                    ->columnSpan(2),
                            ]),

                        Grid::make(4)
                            ->schema([
                                Select::make('opportunity_id')
                                    ->label('Opportunity')
                                    ->relationship('opportunity', 'title')
                                    ->options(fn () => Opportunity::query()
                                        ->where(function ($query) {
                                            $userId = auth()->id();
                                            $query->where('assigned_to', $userId)
                                                ->orWhere('created_by', $userId);
                                        })
                                        ->pluck('title', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Select an opportunity')
                                    ->visible(fn ($get) => $get('order_source') === 'opportunities')
                                    ->required(fn ($get) => $get('order_source') === 'opportunities')
                                    ->live()
                                    ->columnSpan(2)
                                    ->afterStateUpdated(function ($set, $get, ?string $state) {
                                        if (! $state) {
                                            $set('end_customer_id', null);
                                            $set('notes', null);
                                            $set('sales', []);
                                            $set('orderItems', []);

                                            return;
                                        }

                                        $opportunity = Opportunity::with(['customer', 'products.principal', 'creator', 'collaborators'])->find($state);
                                        if (! $opportunity) {
                                            return;
                                        }

                                        $set('end_customer_id', $opportunity->customer_id);
                                        $set('notes', $opportunity->notes);
                                        $salesReps = collect([$opportunity->creator?->id])
                                            ->merge($opportunity->collaborators->pluck('id'))
                                            ->filter()
                                            ->values()
                                            ->toArray();
                                        $set('sales', $salesReps);

                                        $orderItems = $opportunity->products->map(fn ($product) => [
                                            'principal_id' => $product->principal_id,
                                            'item_id' => $product->id,
                                            'quantity' => 1,
                                            'price_type' => 'unit_price',
                                            'unit_price' => $product->unit_price ?? 0,
                                            'current_price' => $product->unit_price ?? 0,
                                            'subtotal' => $product->unit_price ?? 0,
                                        ])->toArray();

                                        $set('orderItems', $orderItems);
                                    }),
                                TextInput::make('payment_method')
                                    ->label('Payment Method')
                                    ->placeholder('e.g. Bank Transfer, Credit Card')
                                    ->columnSpan(2),
                            ]),

                        Grid::make(3)
                            ->schema([
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
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(6)
                            ->schema([
                                Select::make('department_id')
                                    ->label('Department')
                                    ->relationship('department', 'name')
                                    ->default($user?->department_id)
                                    ->required()
                                    ->preload()
                                    ->searchable(),
                                Select::make('sales')
                                    ->label('Sales')
                                    ->multiple()
                                    ->options(fn () => User::where('department_id', Department::where('code', 'SAL')->value('id'))
                                        ->pluck('name', 'id'))
                                    ->default(fn () => $user?->id ? [$user->id] : [])
                                    ->disabled(fn ($get) => $get('order_source') === 'opportunities' && $get('opportunity_id'))
                                    ->preload()
                                    ->searchable(),
                                Select::make('pm_jpm_pe_position_id')
                                    ->label('PM/JPM/PE')
                                    ->options(fn () => Position::whereBetween('level', [Position::RSM_LEVEL, Position::SR_LEVEL])->pluck('name', 'id'))
                                    ->default(null)
                                    ->preload()
                                    ->searchable(),
                                Select::make('spv_position_id')
                                    ->label('Supervisor')
                                    ->options(fn () => Position::where('level', Position::SPV_LEVEL)->pluck('name', 'id'))
                                    ->default(fn () => $findAncestorByLevel($userPosition, Position::SPV_LEVEL))
                                    ->preload()
                                    ->searchable(),
                                Select::make('rsm_asm_position_id')
                                    ->label('RSM/ASM')
                                    ->options(fn () => Position::whereIn('level', [Position::RSM_LEVEL, Position::ASM_LEVEL])->pluck('name', 'id'))
                                    ->default(fn () => $findAncestorByLevel($userPosition, Position::RSM_LEVEL))
                                    ->preload()
                                    ->searchable(),
                                Select::make('head_position_id')
                                    ->label('Head')
                                    ->options(fn () => Position::where('level', Position::DIRECTOR_LEVEL)->pluck('name', 'id'))
                                    ->default(fn () => $findAncestorByLevel($userPosition, Position::DIRECTOR_LEVEL))
                                    ->preload()
                                    ->searchable(),
                            ]),
                    ]),

                Section::make('Customer Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
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
                            ]),

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
                                            ->options(fn () => Principal::all()->pluck('name', 'id'))
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
                                            ->afterStateUpdated(fn ($set, $get) => self::updateLineTotal($set, $get))
                                            ->columnSpan(1),
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix('IDR')
                                            ->live()
                                            ->readOnly(fn ($get) => ! $get('principal_id') || ! $get('item_id'))
                                            ->afterStateUpdated(fn ($set, $get) => $set('subtotal', (int) $get('quantity') * (float) $get('unit_price')))
                                            ->columnSpan(1),
                                    ]),
                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('subtotal')
                                            ->label('Line Total')
                                            ->numeric()
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
                                    ->numeric()
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
                                    ->numeric()
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

    protected static function updateLineTotal($set, $get): void
    {
        $itemId = $get('item_id');
        $quantity = (int) $get('quantity');
        $priceType = $get('price_type') ?? 'unit_price';

        if ($itemId) {
            $product = Product::find($itemId);
            if ($product) {
                $unitPrice = $priceType === 'ecatalog_price'
                    ? ($product->ecatalog_price ?? $product->unit_price ?? 0)
                    : ($product->unit_price ?? 0);
                $set('unit_price', $unitPrice);
                $set('current_price', $unitPrice);
            }
        }

        $unitPrice = (float) $get('unit_price');
        $lineTotal = $quantity * $unitPrice;
        $set('subtotal', $lineTotal);
    }

    protected static function calculateOrderTotals($set, $get): void
    {
        $orderItems = $get('orderItems');
        $grossSales = 0;

        if ($orderItems && is_array($orderItems)) {
            foreach ($orderItems as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $grossSales += $quantity * $unitPrice;
            }
        }

        $set('subtotal', $grossSales);

        $discountPercent = (float) $get('discount_on');
        $netSales = $grossSales * (1 - ($discountPercent / 100));
        $set('total_amount', $netSales);
        $set('net_sales_total', $netSales);
    }

    protected static function calculateNetSales($set, $get): void
    {
        $grossSales = (float) $get('subtotal');
        $discountPercent = (float) $get('discount_on');
        $netSales = $grossSales * (1 - ($discountPercent / 100));
        $set('total_amount', $netSales);
        $set('net_sales_total', $netSales);
    }
}
