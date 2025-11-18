<?php
// FILE: includes/Core/ServiceContainer.php

namespace WPBlogMailer\Core;

// Core & Factories
use WPBlogMailer\Core\EmailServiceFactory;

// Common Components
use WPBlogMailer\Common\Database\Database;
use WPBlogMailer\Common\Services\BaseEmailService;
use WPBlogMailer\Common\Services\SubscriberService;
use WPBlogMailer\Common\Services\CronService;
use WPBlogMailer\Common\Services\TemplateService;
use WPBlogMailer\Common\Services\NewsletterService;
use WPBlogMailer\Common\Services\EmailQueueService;
use WPBlogMailer\Common\Services\SendLogService;
use WPBlogMailer\Common\Services\PredefinedTemplatesService;
use WPBlogMailer\Common\Utilities\Logger;
use WPBlogMailer\Common\Utilities\Validator;
use WPBlogMailer\Common\Analytics\AnalyticsInterface;

// Tiered Components
use WPBlogMailer\Free\SubscribeForm;
use WPBlogMailer\Free\Controllers\SubscribersController;
use WPBlogMailer\Free\Services\BasicTemplateService;
// Note: Starter/Pro classes are loaded conditionally using fully qualified names
// to avoid fatal errors in free version

// Admin & Handlers
use WPBlogMailer\Core\Admin\MenuManager;
use WPBlogMailer\Core\Admin\PageRenderer;
use WPBlogMailer\Core\Admin\AssetManager;
use WPBlogMailer\Core\Handlers\NewsletterHandler;
use WPBlogMailer\Core\Handlers\CustomEmailHandler;
use WPBlogMailer\Core\Handlers\TemplateHandler;
use WPBlogMailer\Core\Handlers\TagHandler;
use WPBlogMailer\Core\Handlers\CronHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Service Container for Dependency Injection.
 */
class ServiceContainer {

    private $services = [];
    private $definitions = [];

    public function __construct() {
        $this->register_services();
    }

    public function get( $id ) {
        if ( isset( $this->services[ $id ] ) ) {
            return $this->services[ $id ];
        }
        if ( ! isset( $this->definitions[ $id ] ) ) {
            // Provide a more specific error message including the class name requested
            throw new \Exception( "Service definition not found for ID: " . esc_html($id) );
        }
        try {
             $this->services[ $id ] = $this->definitions[ $id ]( $this );
        } catch (\Exception $e) {
             // Catch errors during service creation and provide context
             throw new \Exception( "Error creating service '" . esc_html($id) . "': " . esc_html($e->getMessage()), 0, $e );
        }

        return $this->services[ $id ];
    }

    public function set( $id, $callable ) {
        $this->definitions[ $id ] = $callable;
    }

    /**
     * Register all plugin services and their dependencies.
     */
    private function register_services() {

        // --- CORE / UTILITIES ---
        $this->set( Database::class, function( $c ) { return new Database(); });
        $this->set( Logger::class, function( $c ) { return new Logger(); });
        $this->set( Validator::class, function( $c ) { return new Validator(); });

        // --- TIERED ANALYTICS & TRACKING ---
        // Register the concrete Analytics classes only if they exist
        if ( class_exists( '\WPBlogMailer\Starter\Analytics\BasicAnalytics' ) ) {
            $this->set( '\WPBlogMailer\Starter\Analytics\BasicAnalytics', function( $c ) {
                return new \WPBlogMailer\Starter\Analytics\BasicAnalytics( $c->get( Database::class ) );
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' ) ) {
            $this->set( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics', function( $c ) {
                return new \WPBlogMailer\Pro\Analytics\AdvancedAnalytics( $c->get( Database::class ) );
            } );
        }

        // Define which concrete class satisfies the AnalyticsInterface based on plan
        $this->set( AnalyticsInterface::class, function( $c ) {
            // Use the proper helper function to check plan with error handling
            try {
                if ( function_exists('wpbm_is_pro') && wpbm_is_pro() && class_exists( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' ) ) {
                    return $c->get( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' );
                }
                if ( class_exists( '\WPBlogMailer\Starter\Analytics\BasicAnalytics' ) ) {
                    return $c->get( '\WPBlogMailer\Starter\Analytics\BasicAnalytics' );
                }
            } catch (\Exception $e) {
            }
            // Free version: Return null (no analytics interface)
            return null;
        } );

        // Define TrackingService, only created for Pro
        if ( class_exists( '\WPBlogMailer\Pro\Services\TrackingService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\TrackingService', function( $c ) {
                // Use the proper helper function to check plan with error handling
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\TrackingService( $c->get( AnalyticsInterface::class ) );
                    }
                } catch (\Exception $e) {
                }
                return null; // Return null for Free/Starter
            } );
        }

        // --- TIERED EMAIL SERVICE ---
        // Define the Factory, injecting dependencies it needs
        $this->set( EmailServiceFactory::class, function( $c ) {
            // Get TrackingService if it exists (Pro only), otherwise null
            $trackingService = null;
            if ( class_exists( '\WPBlogMailer\Pro\Services\TrackingService' ) ) {
                try {
                    $trackingService = $c->get( '\WPBlogMailer\Pro\Services\TrackingService' );
                } catch (\Exception $e) {
                    // TrackingService not registered, use null
                    $trackingService = null;
                }
            }

            return new EmailServiceFactory(
                $c->get( Logger::class ),
                $c->get( Validator::class ),
                $c->get( AnalyticsInterface::class ), // Container provides correct tier (Basic/Advanced) or null
                $trackingService     // Will be TrackingService instance or null
            );
        } );

        // Register the *result* of the factory using BaseEmailService as the key
        $this->set( BaseEmailService::class, function( $c ) {
            // Ensure EmailServiceFactory has a 'create' method
            return $c->get( EmailServiceFactory::class )->create(); // Factory uses direct FS check
        } );

        // --- COMMON SERVICES ---
        $this->set( SubscriberService::class, function( $c ) {
             // Ensure SubscriberService exists and constructor accepts Database
            return new SubscriberService( $c->get( Database::class ) );
        } );
        $this->set( CronService::class, function( $c ) {
             // Ensure CronService exists
            return new CronService(); // Add dependencies if needed
        } );
        $this->set( TemplateService::class, function( $c ) {
             // Ensure TemplateService exists
            return new TemplateService(); // Add dependencies if needed
        } );
        $this->set( PredefinedTemplatesService::class, function( $c ) {
            return new PredefinedTemplatesService();
        } );

        // --- TEMPLATE SERVICES ---
        $this->set( BasicTemplateService::class, function( $c ) {
            return new BasicTemplateService( $c->get( TemplateService::class ) );
        } );

        if ( class_exists( '\WPBlogMailer\Pro\Services\CustomTemplateService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\CustomTemplateService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\CustomTemplateService( $c->get( Database::class ) );
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        // --- PRO SERVICES (Only registered if classes exist) ---
        if ( class_exists( '\WPBlogMailer\Pro\Services\TemplateLibraryService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\TemplateLibraryService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\TemplateLibraryService();
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\WeeklyReportService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\WeeklyReportService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\WeeklyReportService( $c->get( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' ) );
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\ABTestService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\ABTestService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\ABTestService( $c->get( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' ) );
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\EngagementScoringService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\EngagementScoringService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\EngagementScoringService();
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\TimezoneService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\TimezoneService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\TimezoneService( $c->get( EmailQueueService::class ) );
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\VisualTemplateBuilderService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\VisualTemplateBuilderService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\VisualTemplateBuilderService();
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\AnalyticsExportService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\AnalyticsExportService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\AnalyticsExportService( $c->get( '\WPBlogMailer\Pro\Analytics\AdvancedAnalytics' ) );
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        if ( class_exists( '\WPBlogMailer\Pro\Services\SegmentService' ) ) {
            $this->set( '\WPBlogMailer\Pro\Services\SegmentService', function( $c ) {
                try {
                    if ( function_exists('wpbm_is_pro') && wpbm_is_pro() ) {
                        return new \WPBlogMailer\Pro\Services\SegmentService();
                    }
                } catch (\Exception $e) {
                }
                return null;
            } );
        }

        // --- EMAIL QUEUE SERVICE ---
        $this->set( EmailQueueService::class, function( $c ) {
            return new EmailQueueService(
                $c->get( Database::class ),
                $c->get( Logger::class ),
                $c->get( BaseEmailService::class )
            );
        } );

        // --- SEND LOG SERVICE ---
        $this->set( SendLogService::class, function( $c ) {
            return new SendLogService(
                $c->get( Database::class )
            );
        } );

        // --- NEWSLETTER SERVICE ---
        $this->set( NewsletterService::class, function( $c ) {
            // Try to get Pro services, return null if they don't exist
            $customTemplateService = null;
            $templateLibraryService = null;

            if ( class_exists( '\WPBlogMailer\Pro\Services\CustomTemplateService' ) ) {
                try {
                    $customTemplateService = $c->get( '\WPBlogMailer\Pro\Services\CustomTemplateService' );
                } catch (\Exception $e) {
                    $customTemplateService = null;
                }
            }

            if ( class_exists( '\WPBlogMailer\Pro\Services\TemplateLibraryService' ) ) {
                try {
                    $templateLibraryService = $c->get( '\WPBlogMailer\Pro\Services\TemplateLibraryService' );
                } catch (\Exception $e) {
                    $templateLibraryService = null;
                }
            }

            // Email queue service is only available in Starter+ tier
            // In free tier, emails are sent immediately instead of being queued
            $queueService = null;
            if (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
                $queueService = $c->get( EmailQueueService::class );
            }

            return new NewsletterService(
                $c->get( SubscriberService::class ),
                $c->get( BaseEmailService::class ),
                $c->get( BasicTemplateService::class ),
                $c->get( Logger::class ),
                $customTemplateService,  // Will be null in free version
                $templateLibraryService, // Will be null in free version
                $queueService           // Will be null in free version
            );
        } );

        // --- CONTROLLERS ---
        $this->set( SubscribersController::class, function( $c ) {
             // Ensure SubscribersController exists and constructor accepts SubscriberService
            return new SubscribersController( $c->get( SubscriberService::class ) );
        } );
        $this->set( SubscribeForm::class, function( $c ) {
             // Ensure SubscribeForm exists and constructor accepts SubscriberService and BaseEmailService
            return new SubscribeForm(
                $c->get( SubscriberService::class ),
                $c->get( BaseEmailService::class ) // Gets the correct tiered Email Service
            );
        } );

        // --- ADMIN CLASSES ---
        $this->set( PageRenderer::class, function( $c ) {
            return new PageRenderer(
                defined('WPBM_PLUGIN_PATH') ? WPBM_PLUGIN_PATH : plugin_dir_path(dirname(dirname(__FILE__))),
                $c->get( AnalyticsInterface::class )
            );
        } );

        $this->set( MenuManager::class, function( $c ) {
            return new MenuManager(
                $c->get( PageRenderer::class ),
                $c->get( SubscribersController::class )
            );
        } );

        $this->set( AssetManager::class, function( $c ) {
            return new AssetManager(
                defined('WPBM_VERSION') ? WPBM_VERSION : '2.0.0',
                defined('WPBM_PLUGIN_URL') ? WPBM_PLUGIN_URL : plugin_dir_url(dirname(dirname(__FILE__)))
            );
        } );

        // --- HANDLERS ---
        $this->set( NewsletterHandler::class, function( $c ) {
            return new NewsletterHandler(
                $c->get( NewsletterService::class ),
                $c->get( EmailQueueService::class ),
                $c->get( BaseEmailService::class ),
                $c
            );
        } );

        $this->set( CustomEmailHandler::class, function( $c ) {
            return new CustomEmailHandler(
                $c->get( SubscriberService::class ),
                $c->get( BaseEmailService::class )
            );
        } );

        $this->set( TemplateHandler::class, function( $c ) {
            return new TemplateHandler( $c );
        } );

        $this->set( TagHandler::class, function( $c ) {
            return new TagHandler();
        } );

        $this->set( CronHandler::class, function( $c ) {
            return new CronHandler( $c );
        } );
    }
} // End Class