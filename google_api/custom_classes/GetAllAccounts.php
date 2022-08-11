<?php
/*
 * Copyright 2021 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Gets all accounts for the logged in user.
 *
 * Tags: accounts.list
 */
class GetAllAccounts {
  /**
   * Gets all accounts for the logged in user.
   *
   * @param $service Google_Service_AdSense AdSense service object on which to
   *     run the requests.
   * @param $maxPageSize int the maximum page size to retrieve.
   * @return array the last page of retrieved accounts.
   */
  public static function run($service, $maxPageSize) {
    $optParams['pageSize'] = $maxPageSize;

    $pageToken = null;
    do {
      $optParams['pageToken'] = $pageToken;
      $result = $service->accounts->listAccounts( $optParams );
      $accounts = array();
      if ( ! empty($result['accounts'] ) ) {
        foreach ( $result['accounts'] as $account) {
					$accounts[] = array( 'id' => $account['name'], 'display_name' => $account['displayName'] );
        }
        $pageToken = $result['nextPageToken'];
      } else {
        return $accounts;
      }
    } while ( $pageToken );

    return $accounts;
  }
}
