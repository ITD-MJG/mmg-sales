<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Principal;
use App\Models\Product;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
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
                                        if (! $record) {
                                            return;
                                        }

                                        $component->state(array_values(array_unique(array_filter([
                                            $record->assigned_to,
                                            ...$record->collaborators->pluck('id')->all(),
                                        ]))));
                                    })
                                    ->dehydrated(false)
                                    ->saveRelationshipsUsing(function ($record, ?array $state): void {
                                        $userIds = array_values(array_unique(array_map('intval', $state ?? [])));
                                        $primaryAssigneeId = array_shift($userIds);

                                        $record->assigned_to = $primaryAssigneeId;
                                        $record->save();

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
                                    ->relationship()
                                    ->schema([
                                        Select::make('principal_id')
                                            ->label('Principal')
                                            ->options(Principal::query()->orderBy('name')->pluck('name', 'id'))
                                            ->live()
                                            ->afterStateUpdated(fn ($set) => $set('product_id', null))
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->options(fn (Get $get) => Product::query()
                                                ->where('principal_id', $get('principal_id'))
                                                ->orderBy('name')
                                                ->pluck('name', 'id'))
                                            ->disabled(fn (Get $get): bool => blank($get('principal_id')))
                                            ->rules(fn (Get $get): array => [
                                                'required',
                                                Rule::exists('products', 'id')->where('principal_id', $get('principal_id')),
                                            ])
                                            ->searchable()
                                            ->preload(),
                                    ])
                                    ->columns(2),
                            ]),
                        Section::make('Estimation')
                            ->columnSpan(1)
                            ->columns(2)
                            ->schema([
                                TextInput::make('estimated_revenue')
                                    ->label('Expected Revenue')
                                    ->numeric()
                                    ->prefix('IDR')
                                    ->columnSpanFull(),
                            ]),
                        Section::make('Pipeline & Status')
                            ->columnSpan(1)
                            ->columns(3)
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
                                    ->options([
                                        'new' => 'New',
                                        'contacted' => 'Contacted',
                                        'converted' => 'Converted',
                                        'disqualified' => 'Disqualified',
                                    ])
                                    ->default('new')
                                    ->required()
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
}
