<?php

namespace App\Filament\Resources\MobileUserResource\RelationManagers;

use App\Filament\Resources\GoshenTransactionEntryResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class TransactionEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'transactionEntries';

    protected static ?string $title = 'Financial activity';

    public function table(Table $table): Table
    {
        return GoshenTransactionEntryResource::configureTransactionTable($table, includeMember: false);
    }
}
