<?php

namespace App\Filament\Resources\Manuales;

use App\Filament\Resources\Manuales\Pages\ManageManuales;
use App\Filament\Shared\VerManual;
use App\Models\Manual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Manuales de uso (index.html) asociados a un servicio. Los clientes con ese
 * servicio los consultan desde el menú "Ayuda" del portal.
 */
class ManualResource extends Resource
{
    protected static ?string $model = Manual::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $modelLabel = 'manual';

    protected static ?string $pluralModelLabel = 'manuales';

    protected static ?string $slug = 'manuales';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'titulo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('titulo')
                ->label('Título')
                ->helperText('Nombre del enlace en el menú "Ayuda".')
                ->required()
                ->maxLength(255),
            Select::make('servicio_id')
                ->label('Servicio')
                ->relationship('servicio', 'nombre')
                ->helperText('Solo lo verán los clientes que tengan asignado este servicio.')
                ->searchable()
                ->preload()
                ->required(),
            FileUpload::make('archivo')
                ->label('Archivo index.html')
                ->helperText('Un único archivo HTML (máx. 5 MB). Las imágenes y estilos deben ir incrustados en el propio archivo.')
                ->disk(Manual::DISCO)
                ->directory(Manual::DIRECTORIO)
                ->visibility('private')
                ->acceptedFileTypes(['text/html'])
                ->maxSize(5120)
                ->required()
                ->columnSpanFull(),
            Toggle::make('activo')
                ->helperText('Los manuales inactivos no aparecen en el menú "Ayuda".')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('servicio.nombre')
                    ->label('Servicio')
                    ->sortable(),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('servicio_id')
                    ->label('Servicio')
                    ->relationship('servicio', 'nombre'),
                TernaryFilter::make('activo'),
            ])
            ->recordActions([
                Action::make('ver')
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Manual $record) => VerManual::getUrl(['manual' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('titulo');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageManuales::route('/'),
        ];
    }
}
