<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Models\LeadProduct;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use App\Services\PrincipalScopeService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Validation\Rule;

class LeadForm
{
    /**
     * Masked values arrive grouped with "." and stored decimals with a ".xx"
     * fraction, so the rule only has to reject anything that is not a number.
     */
    private const MONEY_PATTERN = '/^\d+([.,]\d+)*$/';

    /**
     * Set while Expected Revenue is written programmatically, so the field's
     * own update hook does not mistake it for a manual override.
     */
    private static bool $syncingEstimatedRevenue = false;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Lead Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Lead Title')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('customer_id')
                                    ->label('Customer Name')
                                    ->relationship('customer', 'name', fn ($query) => $query->where('status', 'active'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),
                                Select::make('assigned_users')
                                    ->label('Assigned To')
                                    ->multiple()
                                    ->options(User::pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        if ($record) {
                                            $component->state($record->collaborators->modelKeys());
                                        }
                                    })
                                    ->dehydrated(false)
                                    ->saveRelationshipsUsing(function ($record, ?array $state): void {
                                        $userIds = array_values(array_unique(array_map('intval', $state ?? [])));

                                        $record->collaborators()->syncWithPivotValues(
                                            $userIds,
                                            ['added_by' => auth()->id()],
                                        );
                                    })
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Contact Information')
                            ->schema([
                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email(),
                                TextInput::make('phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->required(),
                            ]),
                    ]),

                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Products')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('leadProductItems')
                                    ->label('Products list')
                                    ->relationship()
                                    ->schema([
                                        Select::make('principal_id')
                                            ->label('Principal')
                                            ->options(fn () => PrincipalScopeService::principals(Principal::query(), auth()->user())
                                                ->where('is_active', true)
                                                ->orderBy('name')
                                                ->pluck('name', 'id'))
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, $set): void {
                                                $productId = $get('product_id');

                                                if (! $productId) {
                                                    return;
                                                }

                                                $productPrincipalId = Product::query()->whereKey($productId)->value('principal_id');

                                                if ((int) $productPrincipalId === (int) $state) {
                                                    return;
                                                }

                                                $set('product_id', null);
                                                $set('unit_price', null);
                                                self::updateLineTotal($get, $set);
                                            })
                                            ->searchable()
                                            ->preload(),
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->options(fn (Get $get) => PrincipalScopeService::products(Product::query(), auth()->user())
                                                ->where('is_active', true)
                                                ->when($get('principal_id'), fn ($query, $principalId) => $query->where('principal_id', $principalId))
                                                ->orderBy('name')
                                                ->pluck('name', 'id'))
                                            ->live()
                                            ->afterStateUpdated(function ($state, Get $get, $set): void {
                                                $product = $state ? Product::query()->find($state) : null;

                                                $set('principal_id', $product?->principal_id);
                                                $set('unit_price', $product?->unit_price);
                                                self::updateLineTotal($get, $set);
                                            })
                                            ->rule(function (Get $get) {
                                                $principalId = $get('principal_id');

                                                return $principalId
                                                    ? Rule::exists('products', 'id')->where('principal_id', $principalId)
                                                    : 'exists:products,id';
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->distinct()
                                            ->validationMessages([
                                                'distinct' => 'This product is already listed on another line.',
                                            ])
                                            ->required(),
                                        TextInput::make('quantity')
                                            ->label('Qty')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->live(debounce: 500)
                                            ->afterStateUpdated(fn (Get $get, $set) => self::updateLineTotal($get, $set)),
                                        TextInput::make('unit_price')
                                            ->label('Unit Price')
                                            ->prefix('IDR')
                                            ->mask(RawJs::make('$money($input, ",", ".", 0)'))
                                            ->rule('regex:'.self::MONEY_PATTERN)
                                            ->validationMessages(['regex' => 'Enter a number, e.g. 1.500.000.'])
                                            ->live(debounce: 500)
                                            ->required()
                                            ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : self::parseMoney($state))
                                            ->dehydrateStateUsing(fn ($state) => self::parseMoney($state))
                                            ->afterStateUpdated(fn (Get $get, $set) => self::updateLineTotal($get, $set)),
                                        TextInput::make('line_total')
                                            ->label('Line Total')
                                            ->numeric()
                                            ->prefix('IDR')
                                            ->readOnly()
                                            ->dehydrated(false)
                                            ->afterStateHydrated(fn (TextInput $component, Get $get) => $component->state(self::calculateLineTotal($get)))
                                            ->afterStateUpdated(fn (Get $get, $set) => self::updateLineTotal($get, $set))
                                            ->live(),
                                    ])
                                    ->columns(5),
                            ]),
                        Section::make('Estimation')
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                TextInput::make('total_value')
                                    ->label('Total Value')
                                    ->numeric()
                                    ->prefix('IDR')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                Hidden::make('estimated_revenue_is_manual')
                                    ->dehydrated(false)
                                    ->afterStateHydrated(function (Set $set, ?Lead $record): void {
                                        if (! $record) {
                                            $set('estimated_revenue_is_manual', false);

                                            return;
                                        }

                                        $productsTotal = (float) $record->leadProductItems->sum(
                                            fn (LeadProduct $item): float => (float) $item->quantity * (float) $item->unit_price
                                        );

                                        $set(
                                            'estimated_revenue_is_manual',
                                            round((float) $record->estimated_revenue, 2) !== round($productsTotal, 2),
                                        );
                                    }),
                                TextInput::make('estimated_revenue')
                                    ->label('Expected Revenue')
                                    ->prefix('IDR')
                                    ->mask(RawJs::make('$money($input, ",", ".", 0)'))
                                    ->rule('regex:'.self::MONEY_PATTERN)
                                    ->validationMessages(['regex' => 'Enter a number, e.g. 1.500.000.'])
                                    ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : self::parseMoney($state))
                                    ->dehydrateStateUsing(fn ($state) => self::parseMoney($state))
                                    ->helperText('Automatically taken from the Line Total sum of the Products list above. Change it if you want a different number.')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function (Set $set): void {
                                        if (self::$syncingEstimatedRevenue) {
                                            return;
                                        }

                                        $set('estimated_revenue_is_manual', true);
                                    })
                                    ->columnSpan(1)
                                    ->required(),
                                DatePicker::make('expected_closing_date')
                                    ->label('Expected Closing')
                                    ->native(false)
                                    ->required(),
                            ]),
                        Section::make('Pipeline & Status')
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                Select::make('source')
                                    ->options([
                                        'website' => 'Website',
                                        'referral' => 'Referral',
                                        'cold_call' => 'Cold call',
                                        'trade_show' => 'Trade show',
                                        'partner' => 'Partner',
                                        'other' => 'Other',
                                    ])
                                    ->default('other')
                                    ->required()
                                    ->searchable(),
                                Select::make('priority')
                                    ->options([
                                        'low' => 'Low',
                                        'medium' => 'Medium',
                                        'high' => 'High',
                                        'urgent' => 'Urgent',
                                    ])
                                    ->default('medium')
                                    ->required()
                                    ->searchable(),
                                Select::make('status')
                                    ->options(Lead::STATUS_LABELS)
                                    ->default('new')
                                    ->required()
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                        'confidence_level',
                                        Lead::defaultConfidenceForStatus($state),
                                    )),
                                TextInput::make('confidence_level')
                                    ->label('Confidence Level')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(1)
                                    ->suffix('%')
                                    ->helperText('How likely this lead is to close, from 0 to 100%. Leave empty until you have actually judged it.')
                                    ->placeholder('Not assessed'),
                            ]),
                        Section::make('Notes')
                            ->columnSpanFull()
                            ->schema([
                                Textarea::make('notes')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    private static function calculateLineTotal(Get $get): float
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitPrice = self::parseMoney($get('unit_price'));

        return $quantity * $unitPrice;
    }

    private static function calculateItemTotal(array $item): float
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = self::parseMoney($item['unit_price'] ?? 0);

        return $quantity * $unitPrice;
    }

    /**
     * Turn either a masked display value ("1.500.000") or a stored decimal
     * ("15000.00") into a float. A stored decimal always carries a one or two
     * digit fraction, which the mask never produces, so the two are
     * unambiguous.
     */
    private static function parseMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        if (preg_match('/^\d+\.\d{1,2}$/', $value) === 1) {
            return (float) $value;
        }

        return (float) str_replace('.', '', $value);
    }

    private static function updateLineTotal(Get $get, $set): void
    {
        $lineTotal = self::calculateLineTotal($get);

        $set('line_total', $lineTotal);
        self::updateTotalValue($get, $set);
    }

    private static function updateTotalValue(Get $get, $set): void
    {
        $items = $get('../../leadProductItems') ?? [];
        $total = collect($items)->sum(fn (array $item): float => self::calculateItemTotal($item));

        $set('../../total_value', $total);
        self::syncEstimatedRevenue($get, $set, $total);
    }

    /**
     * Mirrors the products total into Expected Revenue until the user types
     * their own figure, which is then left alone.
     */
    private static function syncEstimatedRevenue(Get $get, $set, float $total): void
    {
        if ($get('../../estimated_revenue_is_manual')) {
            return;
        }

        self::$syncingEstimatedRevenue = true;

        try {
            $set('../../estimated_revenue', $total);
        } finally {
            self::$syncingEstimatedRevenue = false;
        }
    }
}
