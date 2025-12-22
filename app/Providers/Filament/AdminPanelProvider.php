<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use App\Filament\Widgets\DashboardHeader;
use App\Filament\Widgets\LatestTransactions;
use App\Filament\Widgets\LatestLowStockItems;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TransactionChart;
use App\Filament\Widgets\WarehouseValueOverview;
use App\Filament\Widgets\CategoryValueChart;
use App\Filament\Widgets\RecentActivityWidget;
use App\Filament\Widgets\WarehouseValuationTable;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('G.A.S.S. | GA Stock System')
            ->sidebarCollapsibleOnDesktop()
            ->brandName('GA Stock System') // Tulisan di Pojok Kiri
            ->favicon(asset('images/favicon.ico')) // (Opsional) Ikon di tab browser
            ->colors([
                'primary' => Color::Slate,
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            //->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([


                //DashboardHeader::class,
                //WarehouseValueOverview::class,

                StatsOverview::class,
                TransactionChart::class,
                CategoryValueChart::class,
                LatestTransactions::class,
                LatestLowStockItems::class,
                //RecentActivityWidget::class,
                WarehouseValuationTable::class,

                //LatestTransactions::class,
                //Widgets\AccountWidget::class,
                //Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])

            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn() => Blade::render(<<<HTML
                <div class="flex items-center justify-center w-full p-4 text-xs font-medium text-gray-500 bg-white dark:bg-gray-900 dark:text-gray-400 border-t border-gray-200 dark:border-gray-800">
                    <span>
                        &copy; 2025 General Affairs Stock System. Built with <span class="text-red-500">❤</span> by 
                        <a href="https://www.instagram.com/faishalma_" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline">Faishal Muhammad</a>.
                    </span>
                </div>
            HTML)
            );
    }
}
