<?php

namespace PowerAPI\Data;

/** Handles fetching the transcript and holding onto its data.
 * @property array $sections contains the student's sections
 * @property arrray $information contains the student's information
 */
class Student extends BaseObject
{
    /** URL for the PowerSchool server
     * @var string
     */
    private $soap_url;

    /** session object as returned by PowerSchool
     * @var array
     */
    private $soap_session;


    /**
     * Attempt to authenticate against the server
     * @param string $soap_url URL for the PowerSchool server
     * @param string $soap_session session object as returned by PowerSchool
     * @param boolean $populate should the transcript be immediately populated?
     */
    public function __construct($soap_url, $soap_session, $populate)
    {
        $this->soap_url = $soap_url;
        $this->soap_session = $soap_session;

        $this->details['information'] = Array();
        $this->details['sections'] = Array();

        if ($populate) {
            $this->populate();
        }
    }

    /**
     * Pull the authenticated user's transcript from the server and parses it.
     * @return null
     */
    public function populate()
    {
        $transcript = $this->fetchTranscript();
        $this->parseTranscript($transcript);
    }

    /**
     * Fetches the user's transcript from the server and returns it.
     * @return array user's transcript as returned by PowerSchool
     */
    public function fetchTranscript()
    {
        $client = new \Zend\Soap\Client();
        $client->setOptions(Array(
            'uri' => 'http://publicportal.rest.powerschool.pearson.com/xsd',
            'location' => $this->soap_url.'pearson-rest/services/PublicPortalServiceJSON',
            'login' => 'pearson',
            'password' => 'm0bApP5',
            'use' => SOAP_LITERAL
        ));

        // This is a workaround for SoapClient not having a WSDL to go off of.
        // Passing everything as an object or as an associative array causes
        // the parameters to not be correctly interpreted by PowerSchool.
        $parameters = Array(
            'userSessionVO' => (object) Array(
                'userId' => $this->soap_session->userId,
                'serviceTicket' => $this->soap_session->serviceTicket,
                'serverInfo' => (object) Array(
                    'apiVersion' => $this->soap_session->serverInfo->apiVersion
                ),
                'serverCurrentTime' => $this->soap_session->serverCurrentTime,
                'userType' => $this->soap_session->userType
            ),
            'studentIDs' => $this->soap_session->studentIDs,
            'qil' => (object) Array(
                'includes' => '1'
            )
        );

        $transcript = $client->__call('getStudentData', $parameters);

        return $transcript;
    }

    /**
     * Parses the passed transcript and populates $this with its contents.
     * @param object $transcript transcript from fetchTranscript()
     * @return void
     */
    public function parseTranscript($transcript)
    {
        $studentData = $transcript->studentDataVOs;

        $this->details['information'] = $studentData->student;

        $assignmentCategories = \PowerAPI\Parser::assignmentCategories($studentData->assignmentCategories);
        $assignmentScores = \PowerAPI\Parser::assignmentScores($studentData->assignmentScores);
        $finalGrades = \PowerAPI\Parser::finalGrades($studentData->finalGrades);
        $reportingTerms = \PowerAPI\Parser::reportingTerms($studentData->reportingTerms);
        $teachers = \PowerAPI\Parser::teachers($studentData->teachers);

        $assignments = \PowerAPI\Parser::assignments(
            $studentData->assignments,
            $assignmentCategories,
            $assignmentScores
        );

        $this->details['sections'] = \PowerAPI\Parser::sections(
            $studentData->sections,
            $assignments,
            $finalGrades,
            $reportingTerms,
            $teachers
        );
    }
}
