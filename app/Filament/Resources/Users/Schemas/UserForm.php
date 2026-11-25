<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required(),
                    ]),

                Section::make('Organization & Hierarchy')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->default(null)
                            ->preload()
                            ->searchable()
                            ->live(),
                        Select::make('position_id')
                            ->relationship('position', 'name')
                            ->default(null)
                            ->preload()
                            ->searchable(),
                        Select::make('territory_id')
                            ->relationship('territory', 'name')
                            ->default(null)
                            ->preload()
                            ->searchable(),
                        Select::make('manager_id')
                            ->relationship('manager', 'name', function ($query) {
                                $query->whereHas('roles', function ($q) {
                                    $q->where('name', 'like', '%Supervisor%')
                                        ->orWhere('name', 'like', '%Manager%')
                                        ->orWhere('name', 'like', '%Director%');
                                });
                            })
                            ->default(null)
                            ->preload()
                            ->searchable(),

                        // Department-filtered role assignment
                        Select::make('roles')
                            ->relationship('roles', 'name', function ($query, $get) {
                                $departmentId = $get('department_id');
                                if ($departmentId) {
                                    $query->where(function ($q) use ($departmentId) {
                                        $q->whereNull('department_id')
                                            ->orWhere('department_id', $departmentId);
                                    });
                                }
                            })
                            ->multiple()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Only global roles and roles matching the user\'s department are shown.'),

                        // Marketing users are restricted to the principals
                        // assigned here. Empty means no principals at all.
                        Select::make('principals')
                            ->label('Principal Responsibility')
                            ->relationship('principals', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => Department::query()
                                ->whereKey($get('department_id'))
                                ->where('name', Department::MARKETING)
                                ->exists())
                            ->helperText('Marketing users see only the leads, orders and products of these principals.'),
                    ]),
            ]);
    }
}
