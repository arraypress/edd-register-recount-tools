# EDD Recount Tools - Register Custom Recount Tools

Add custom recount/batch processing tools to Easy Digital Downloads admin Tools page.

## Installation

```bash
composer require arraypress/edd-register-recount-tools
```

## Basic Usage (Simple Callbacks)

```php
// Register callback-based recount tools (recommended)
edd_register_recount_tools( [
    'recount-customer-downloads' => [
        'label'          => 'Customer Download Counts',
        'description'    => 'Recalculates the total number of file downloads for each customer.',
        'batch_size'     => 20,
        'count_callback' => function() {
            return edd_count_customers();
        },
        'callback'       => function( $offset, $batch_size ) {
            $customers = edd_get_customers( [
                'number' => $batch_size,
                'offset' => $offset,
                'fields' => 'id'
            ] );

            foreach ( $customers as $customer_id ) {
                $customer = new EDD_Customer( $customer_id );
                
                // Count file downloads for this customer
                $download_count = edd_count_file_downloads_of_customer( $customer->id );
                
                // Update customer meta
                edd_update_customer_meta( $customer->id, 'total_downloads', $download_count );
                
                edd_debug_log( sprintf( 'Updated download count for customer #%d: %d downloads', $customer->id, $download_count ) );
            }

            return $customers; // Return the processed items
        }
    ],
    'recount-product-reviews' => [
        'label'          => 'Product Review Counts',
        'description'    => 'Updates the review count and average rating for all downloads.',
        'batch_size'     => 10,
        'count_callback' => function() {
            return wp_count_posts( 'download' )->publish;
        },
        'callback'       => function( $offset, $batch_size ) {
            $downloads = get_posts( [
                'post_type'      => 'download',
                'posts_per_page' => $batch_size,
                'offset'         => $offset,
                'fields'         => 'ids'
            ] );

            foreach ( $downloads as $download_id ) {
                // Count reviews
                $review_count = get_comments_number( $download_id );
                
                // Calculate average rating
                $reviews = get_comments( [
                    'post_id'      => $download_id,
                    'meta_key'     => 'rating',
                    'meta_compare' => 'EXISTS'
                ] );

                $total_rating = 0;
                $rating_count = 0;

                foreach ( $reviews as $review ) {
                    $rating = get_comment_meta( $review->comment_ID, 'rating', true );
                    if ( $rating ) {
                        $total_rating += (int) $rating;
                        $rating_count++;
                    }
                }

                $average_rating = $rating_count > 0 ? round( $total_rating / $rating_count, 2 ) : 0;

                // Update post meta
                update_post_meta( $download_id, '_edd_review_count', $review_count );
                update_post_meta( $download_id, '_edd_average_rating', $average_rating );
            }

            return $downloads;
        }
    ]
] );
```

## Real Examples

### Sync Customer Data with CRM

```php
edd_register_recount_tools( [
    'sync-crm-data' => [
        'label'          => 'Sync CRM Data',
        'description'    => 'Synchronizes customer purchase data with external CRM system.',
        'batch_size'     => 5, // Smaller batches for API calls
        'count_callback' => function() {
            return edd_count_customers();
        },
        'callback'       => function( $offset, $batch_size ) {
            $customers = edd_get_customers( [
                'number' => $batch_size,
                'offset' => $offset
            ] );

            foreach ( $customers as $customer ) {
                // Prepare customer data for CRM
                $crm_data = [
                    'email'         => $customer->email,
                    'name'          => $customer->name,
                    'total_spent'   => $customer->purchase_value,
                    'order_count'   => $customer->purchase_count,
                    'last_purchase' => $customer->date_created
                ];

                // Send to CRM
                $response = wp_remote_post( 'https://your-crm.com/api/customers', [
                    'body' => json_encode( $crm_data ),
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . get_option( 'crm_api_key' )
                    ]
                ] );

                if ( is_wp_error( $response ) ) {
                    edd_debug_log( sprintf( 'CRM sync failed for customer #%d: %s', $customer->id, $response->get_error_message() ), true );
                } else {
                    // Mark as synced
                    edd_update_customer_meta( $customer->id, 'crm_synced', current_time( 'timestamp' ) );
                }

                // Rate limiting
                sleep( 1 );
            }

            return array_column( $customers, 'id' );
        }
    ]
] );
```

### Update Custom Fields

```php
edd_register_recount_tools( [
    'update-lifetime-value' => [
        'label'          => 'Calculate Lifetime Value',
        'description'    => 'Calculates and updates customer lifetime value predictions.',
        'batch_size'     => 15,
        'count_callback' => function() {
            return edd_count_customers();
        },
        'callback'       => function( $offset, $batch_size ) {
            $customers = edd_get_customers( [
                'number' => $batch_size,
                'offset' => $offset
            ] );

            foreach ( $customers as $customer ) {
                if ( $customer->purchase_count < 2 ) {
                    $ltv = $customer->purchase_value;
                } else {
                    // Complex LTV calculation
                    $payments = edd_get_payments( [
                        'customer' => $customer->id,
                        'status'   => 'publish',
                        'number'   => -1
                    ] );
                    
                    $purchase_dates = array_map( function( $payment ) {
                        return strtotime( $payment->date );
                    }, $payments );
                    
                    sort( $purchase_dates );
                    
                    // Calculate average time between purchases
                    $intervals = [];
                    for ( $i = 1; $i < count( $purchase_dates ); $i++ ) {
                        $intervals[] = $purchase_dates[$i] - $purchase_dates[$i-1];
                    }
                    
                    $avg_interval = array_sum( $intervals ) / count( $intervals );
                    $avg_days = $avg_interval / DAY_IN_SECONDS;
                    
                    // Predict 2 years of purchases
                    $predicted_purchases = (365 * 2) / $avg_days;
                    $avg_order_value = $customer->purchase_value / $customer->purchase_count;
                    
                    $ltv = round( $predicted_purchases * $avg_order_value, 2 );
                }

                // Update customer meta
                edd_update_customer_meta( $customer->id, 'predicted_ltv', $ltv );
            }

            return array_column( $customers, 'id' );
        }
    ]
] );
```

## Advanced Usage (Custom Classes)

For complex processing, you can still use custom classes:

```php
edd_register_recount_tools( [
    'complex-recount' => [
        'class'       => 'EDD_Batch_Complex_Recount',
        'file'        => __DIR__ . '/includes/class-complex-recount.php',
        'label'       => 'Complex Data Processing',
        'description' => 'Performs complex multi-step data processing.'
    ]
] );
```

## Configuration Options

### Callback-Based Tools

| Option | Required | Description |
|--------|----------|-------------|
| `callback` | **Yes** | Function that processes items (`function($offset, $batch_size)`) |
| `count_callback` | **Yes** | Function that returns total item count |
| `label` | No | Display name (auto-generated from key if not provided) |
| `description` | No | Description shown in admin interface |
| `batch_size` | No | Items per batch (default: 20) |

### Class-Based Tools

| Option | Required | Description |
|--------|----------|-------------|
| `class` | **Yes** | PHP class name that extends `EDD_Batch_Export` |
| `file` | **Yes** | Full path to file containing the class |
| `label` | No | Display name (auto-generated from key if not provided) |
| `description` | No | Description shown in admin interface |

## Callback Guidelines

1. **Return processed items** - Your callback should return the items it processed
2. **Handle pagination** - Use `$offset` and `$batch_size` parameters
3. **Keep batches small** - 10-50 items per batch to avoid timeouts
4. **Include logging** - Use `edd_debug_log()` for progress tracking
5. **Handle errors gracefully** - Use try/catch blocks
6. **Rate limiting** - Add delays for external API calls

## Requirements

- PHP 7.4+
- WordPress 5.0+
- Easy Digital Downloads 3.0+

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/edd-register-custom-stats)
- [Issue Tracker](https://github.com/arraypress/edd-register-custom-stats/issues)