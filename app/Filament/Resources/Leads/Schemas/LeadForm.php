<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Illuminate\Validation\Rule;

class LeadForm
{
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
                                            ->options(fn () => Principal::query()
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
                                            ->options(fn (Get $get) => Product::query()
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
                                            ->numeric()
                                            ->prefix('IDR')
                                            ->mask(RawJs::make('$money($input, ",", ".", 0)'))
                                            ->stripCharacters('.')
                                            ->live(debounce: 500)
                                            ->required()
                                            ->dehydrateStateUsing(fn ($state) => (float) str_replace('.', '', (string) $state))
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
                                TextInput::make('estimated_revenue')
                                    ->label('Expected Revenue')
                                    ->numeric()
                                    ->prefix('IDR')
                                    ->mask(RawJs::make('$money($input, ",", ".", 0)'))
                                    ->stripCharacters('.')
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
                                    ->searchable(),
                                Select::make('confidence_level')
                                    ->label('Confidence Level')
                                    ->options(Lead::CONFIDENCE_LABELS)
                                    ->helperText('How likely this lead is to close. Leave empty until you have actually judged it.')
                                    ->placeholder('Not assessed')
                                    ->searchable(),
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
        $unitPrice = (float) str_replace('.', '', (string) ($get('unit_price') ?? 0));

        return $quantity * $unitPrice;
    }

    private static function calculateItemTotal(array $item): float
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $unitPrice = (float) str_replace('.', '', (string) ($item['unit_price'] ?? 0));

        return $quantity * $unitPrice;
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
    }
}
