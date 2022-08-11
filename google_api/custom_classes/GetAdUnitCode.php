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
 * This example gets all ad units in an ad client.
 *
 * Tags: accounts.adunits.list
 */
class GetAdUnitCode {
  /**
   * Gets all ad units in an ad client.
   *
   * @param $service Google_Service_AdSense AdSense service object on which to
   *     run the requests.
   * @param $adUnitId string the ID for the ad unit to be used.
   * @param $maxPageSize int the maximum page size to retrieve.
   * @return array the last page of retrieved ad units.
   */
  public static function run($service, $adUnitId) {
    $adCode = '';
		$result = $service->accounts_adclients_adunits->getAdcode( $adUnitId, array() );
		if ( ! empty( $result['adCode'] ) ) {
			$adCode = $result['adCode'];
		}

    return $adCode;
  }
}
