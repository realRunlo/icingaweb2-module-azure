<?php

namespace Icinga\Module\Azure;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This is your main entry point when querying Express Route Circuit from
 * Azure API.
 *
 */


class ExpGWauth extends Api
{
    /**
     * Log Message for getAll
     *
     * @staticvar string MSG_LOG_GET_ALL
     */
    protected const
        MSG_LOG_GET_ALL =
        "Azure API: querying any Express Route Circuits Authorizations ".
        "in configured resource groups.";

    /**
     * static array with names of fields that get configured in a form extension
     * delivered by this class, cf. function extendForm().
     *
     * @staticvar string CONFIG_FIELDS
     */
    public const CONFIG_FIELDS = [ 'express_route_circuits' ];

    /**
     * array of field names to be returned by implementation.
     *
     * @staticvar array FIELDS_RETURNED
     */
    public const FIELDS_RETURNED = array(
        'name',
        'subscriptionId',
        'expressRouteCircuitName',
        'id',
        'type',
        'provisioningState',
        'authorizationKey',
        'authorizationUseStatus',
        'etags',
        'metricDefinitions',
        'authorizationKey',
        'authorizationUseStatus',
        'provisioningState',
    );


    /** ***********************************************************************
     * Generates an dictionary for Express route Ciruits available in given
     * resource group.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    protected function enumExpressRouteCircuits( $resgroupname )
    {

        $resgroups = $this->getResourceGroups( $resgroupname );

        $retval = array();

        foreach( $resgroups as $group )
        {
            $erc = $this->getExpressRouteCircuits( $group );
            foreach($erc as $circuit)
            {
                $retval[$circuit->id] = $circuit->name;
            }
        }

        return $retval;
    }


    /** ***********************************************************************
     * callback for the importer form manager to call for extensions of
     * the config form. This subclass needs the name of the dependent
     * express route circuit. For uniqueness, we save the ID not the name.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    public function extendForm( QuickForm $form ) {

        $rgn = $form->getSentOrObjectSetting('resource_group_names');

        $form->addElement('select', 'express_route_circuits', array(
            'label'        => $form->translate('Express Route Circuit'),
            'description'  => $form->translate(
                'Select the Express Route Circuit you want to query. '),
            'required'     => true,
            'multiOptions' => $form->optionalEnum(
                $this->enumExpressRouteCircuits($rgn)
            ),
        ));
        return;
    }


    /** ***********************************************************************
     * takes all information on express route circuits from a resource group and
     * returns it in the format IcingaWeb2 Director expects
     *
     * @return array of objects
     *
     */

    public function scanResourceGroup($group)
    {
        // log if there are resource groups with surprising provisioning state
        if ($group->properties->provisioningState != "Succeeded")
        {
            Logger::info("Azure API: Resoure group ".$group->name.
                         " invalid provisioning state.");
        }


        // prepare storage for return values
        $objects = array();

        // load express route circuits in this res group
        $exp_circuits = $this->getgetExpressRouteCircuits( $group );

        // search for the right one...
        foreach($exp_circuits as $circuit)
        {
            if ($circuit->id == $this->config['express_route_circuits'])
            {
                // get data needed
                $exp_routes_auth =
                                 $this->getExpressRouteCircuitsAuthorizations(
                                     $group, $circuit->name );

                foreach($exp_routes_auth as $current)
                {
                    // get metric definitions list
                    $metrics = $this->getMetricDefinitionsList($current->id);

                    $object = (object) [
                        'name'                     => $current->name,
                        'expressRouteCircuitName'  => $circuit->name,
                        'subscriptionId'           => $this->subscription_id,
                        'type'                     =>
                        'Microsoft.Network/expressRouteCircuits/authorizations',
                        'id'                       => $current->id,
                        'etag'                     => $current->etag,
                        'metricDefinitions'        => $metrics,
                        'authorizationKey'         => (
                            property_exists(
                                $current->properties, 'authorizationKey') ?
                            $current->properties->authorizationKey : NULL
                        ),
                        'authorizationUseStatus'   => (
                            property_exists(
                                $current->properties,
                                'authorizationUseStatus' ) ?
                            $current->properties->authorizationUseStatus : NULL
                        ),
                        'provisioningState'        => (
                            property_exists(
                                $current->properties, 'provisioningState') ?
                            $current->properties->provisioningState : NULL
                        ),
                    ];
                    // add this to the list.
                    $objects[] = $object;
                }
            }
        }
        return $objects;
    }
}