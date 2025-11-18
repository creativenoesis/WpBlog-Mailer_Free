<?php
// FILE: includes/Core/EmailServiceFactory.php

namespace WPBlogMailer\Core;

// Use statements for dependencies
use WPBlogMailer\Common\Utilities\Logger;
use WPBlogMailer\Common\Utilities\Validator;
use WPBlogMailer\Common\Analytics\AnalyticsInterface;
use WPBlogMailer\Common\Services\BaseEmailService; // Base class/interface
use WPBlogMailer\Free\Services\EmailServiceFree;     // Free implementation
// Note: Starter/Pro classes are loaded conditionally using fully qualified names
// to avoid fatal errors in free version

defined( 'ABSPATH' ) || exit;

class EmailServiceFactory {

    private $logger;
    private $validator;
    private $analyticsService;
    private $trackingService;

    /**
     * Constructor - Accept injected dependencies.
     */
    public function __construct(
        Logger $logger,
        Validator $validator,
        ?AnalyticsInterface $analyticsService = null, // Accept nullable Interface for free version
        $trackingService = null // Accept any type, nullable for free/starter
    ) {
        $this->logger = $logger;
        $this->validator = $validator;
        $this->analyticsService = $analyticsService; // This will be Basic or Advanced instance, or null
        $this->trackingService = $trackingService;   // This will be TrackingService or null
    }

    /**
     * Creates the appropriate Email Service based on the current plan.
     * Uses Freemius helper functions for plan checking.
     *
     * @return BaseEmailService
     */
    public function create(): BaseEmailService {
        // Use helper functions to check plan with error handling
        $tier = 'free'; // Default

        try {
            // Check most specific first
            // Ensure Freemius functions exist and are callable
            if (function_exists('wpbm_is_pro') && wpbm_is_pro()) {
                $tier = 'pro';
            } elseif (function_exists('wpbm_is_starter') && wpbm_is_starter()) {
                $tier = 'starter';
            }
        } catch (\Exception $e) {
            // If Freemius functions throw an exception, log it and default to free
            $tier = 'free';
        }

        // Instantiate based on determined tier
        switch($tier) {
            case 'pro':
                // Only create Pro service if the class exists
                if ( class_exists( '\WPBlogMailer\Pro\Services\EmailServicePro' ) ) {
                    // Ensure Pro service constructor accepts all needed dependencies
                    // The TrackingService should only be non-null if we are in this 'pro' case
                    if (null === $this->trackingService) {
                        // This indicates a potential logic error in ServiceContainer if Pro plan is active
                        // but TrackingService wasn't created. Fallback or log error.
                        // Fallback to starter
                        if ( class_exists( '\WPBlogMailer\Starter\Services\EmailServiceStarter' ) ) {
                            return new \WPBlogMailer\Starter\Services\EmailServiceStarter(
                                $this->logger,
                                $this->validator,
                                $this->analyticsService // Will be AdvancedAnalytics instance here
                            );
                        }
                        // Ultimate fallback to free
                        return new EmailServiceFree(
                            $this->logger,
                            $this->validator
                        );
                    }
                    return new \WPBlogMailer\Pro\Services\EmailServicePro(
                        $this->logger,
                        $this->validator,
                        $this->analyticsService, // Will be AdvancedAnalytics instance here
                        $this->trackingService   // Will be TrackingService instance here
                    );
                }
                // If Pro class doesn't exist, fallback to starter
                // Fall through to starter case
            case 'starter':
                // Only create Starter service if the class exists
                if ( class_exists( '\WPBlogMailer\Starter\Services\EmailServiceStarter' ) ) {
                    return new \WPBlogMailer\Starter\Services\EmailServiceStarter(
                        $this->logger,
                        $this->validator,
                        $this->analyticsService // Will be BasicAnalytics instance here (or Advanced if Pro fallback)
                    );
                }
                // If Starter class doesn't exist, fallback to free
                // Fall through to default case
            default: // free
                // Free service always exists
                return new EmailServiceFree(
                    $this->logger,
                    $this->validator
                );
        }
    }
} // End Class