<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Lead & customer context alongside core activity information
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Lead & Customer')
                            ->columns(2)
                            ->schema([
                                Select::make('lead_id')
                                    ->label('Lead')
                                    ->relationship(
                                        name: 'lead',
                                        titleAttribute: 'title',
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (Lead $record) => "{$record->lead_code} - {$record->customer_name}")
                                    ->searchable(['lead_code', 'title', 'customer_name'])
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $set('customer_id', null);
                                        if ($lead = Lead::find($state)) {
                                            $set('customer_id', $lead->customer_id);
                                        }
                                    }),

                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'name', fn ($query) => $query->where('status', 'active'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->dehydrated(),

                                Action::make('createLead')
                                    ->label('Create Lead')
                                    ->icon('heroicon-m-plus')
                                    ->color('primary')
                                    ->visible(fn ($get): bool => filled($get('customer_id')) && blank($get('lead_id')))
                                    ->form([
                                        TextInput::make('title')
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('status')
                                            ->options([
                                                'new' => 'New',
                                                'contacted' => 'Contacted',
                                                'qualified' => 'Qualified',
                                                'proposal' => 'Proposal',
                                                'negotiation' => 'Negotiation',
                                                'won' => 'Won',
                                                'lost' => 'Lost',
                                            ])
                                            ->default('new'),
                                        Select::make('priority')
                                            ->options([
                                                'low' => 'Low',
                                                'medium' => 'Medium',
                                                'high' => 'High',
                                            ])
                                            ->default('medium'),
                                    ])
                                    ->action(function (array $data, callable $get, callable $set): void {
                                        $customerId = $get('customer_id');
                                        $customer = Customer::find($customerId);

                                        $lead = Lead::create([
                                            'title' => $data['title'],
                                            'status' => $data['status'],
                                            'priority' => $data['priority'],
                                            'customer_id' => $customerId,
                                            'customer_name' => $customer?->name,
                                            'created_by' => auth()->id(),
                                        ]);

                                        $set('lead_id', $lead->id);
                                    }),
                            ]),

                        Section::make('Core Information')
                            ->columns(2)
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        'Call' => 'Call',
                                        'Email' => 'Email',
                                        'Messaging' => 'Messaging (WA/etc)',
                                        'Online Meeting' => 'Online Meeting',
                                        'In-person Meeting' => 'In-person Meeting',
                                        'Shared Meeting' => 'Shared Meeting',
                                        'Demo' => 'Product Demo',
                                        'Presentation' => 'Presentation',
                                        'Administrative' => 'Administrative',
                                    ])
                                    ->required()
                                    ->live(),

                                TextInput::make('subject')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('user_id')
                                    ->label('Sales Rep')
                                    ->relationship('user', 'name')
                                    ->default(auth()->user()?->id)
                                    ->required()
                                    ->preload()
                                    ->searchable(),

                                DateTimePicker::make('performed_at')
                                    ->label('Date & Time')
                                    ->required()
                                    ->default(now()),

                                Select::make('attendees')
                                    ->label('Other Sales Reps')
                                    ->relationship('attendees', 'name')
                                    ->multiple()
                                    ->preload()
                                    ->searchable()
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => $get('type') === 'Shared Meeting'),

                                Select::make('contact_id')
                                    ->label('Contact Person')
                                    ->options(fn ($get) => Contact::where('customer_id', $get('customer_id'))->where('status', 'active')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn ($get) => $get('customer_id')),
                            ]),
                    ]),

                // Core Information + Interaction Details (side-by-side)
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([

                        Section::make('Interaction Details')
                            ->columns(2)
                            ->visible(fn ($get) => in_array($get('type'), ['Online Meeting', 'In-person Meeting', 'Shared Meeting', 'Demo', 'Presentation']))
                            ->schema([
                                TextInput::make('location')
                                    ->columnSpanFull()
                                    ->maxLength(255)
                                    ->visible(fn ($get) => in_array($get('type'), ['In-person Meeting', 'Shared Meeting'])),

                                TextInput::make('meeting_link')
                                    ->url()
                                    ->visible(fn ($get) => in_array($get('type'), ['Online Meeting', 'Shared Meeting'])),

                                Select::make('messaging_platform')
                                    ->options([
                                        'WhatsApp' => 'WhatsApp',
                                        'Telegram' => 'Telegram',
                                        'Other' => 'Other',
                                    ])
                                    ->visible(fn ($get) => $get('type') === 'Messaging'),

                                DateTimePicker::make('visit_started_at')
                                    ->label('Started At')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $endedAt = $get('visit_ended_at');
                                        if ($state && $endedAt) {
                                            $start = Carbon::parse($state);
                                            $end = Carbon::parse($endedAt);
                                            $set('duration_minutes', $start->diffInMinutes($end));
                                        }
                                    }),

                                DateTimePicker::make('visit_ended_at')
                                    ->label('Ended At')
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                        $startedAt = $get('visit_started_at');
                                        if ($startedAt && $state) {
                                            $start = Carbon::parse($startedAt);
                                            $end = Carbon::parse($state);
                                            $set('duration_minutes', $start->diffInMinutes($end));
                                        }
                                    }),

                                Hidden::make('duration_minutes'),

                                Select::make('outcome')
                                    ->columnSpanFull()
                                    ->options([
                                        'Interested' => 'Interested',
                                        'No Answer' => 'No Answer',
                                        'Postponed' => 'Postponed',
                                        'Need more info' => 'Need more info',
                                        'Not Interested' => 'Not Interested',
                                    ]),
                            ]),
                    ]),

                // Follow-up & Planning + Notes & Feedback (side-by-side)
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Follow-up & Planning')
                            ->columns(2)
                            ->schema([
                                Toggle::make('is_worth_keeping')
                                    ->label('Is this lead/project worth keeping?')
                                    ->default(true),

                                DatePicker::make('next_contact_date')
                                    ->label('Next Contact Date'),

                                Textarea::make('follow_up_notes')
                                    ->columnSpanFull()
                                    ->rows(2),
                            ]),

                        Section::make('Notes & Feedback')
                            ->schema([
                                Textarea::make('description')
                                    ->label('Summary Notes')
                                    ->rows(3)
                                    ->columnSpanFull(),

                                Grid::make(2)
                                    ->visible(fn ($get) => in_array($get('type'), ['Online Meeting', 'In-person Meeting', 'Shared Meeting', 'Demo', 'Presentation']))
                                    ->schema([
                                        Textarea::make('purpose')->rows(2),
                                        Textarea::make('expectations')->rows(2),
                                        Textarea::make('targets')->rows(2),
                                        Textarea::make('stakeholder_feedback')->rows(2),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
