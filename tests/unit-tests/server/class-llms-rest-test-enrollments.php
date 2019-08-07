<?php
/**
 * Tests for Enrollments API.
 *
 * @package LifterLMS_Rest/Tests
 *
 * @group REST
 * @group rest_enrollments
 *
 * @since [version]
 * @version [version]
 */
class LLMS_REST_Test_Enrollments extends LLMS_REST_Unit_Test_Case_Server {

	/**
	 * Route.
	 *
	 * @var string
	 */
	private $route = '/llms/v1/students/(?P<id>[\d]+)/enrollments';

	/**
	 * Setup our test server, endpoints, and user info.
	 */
	public function setUp() {
		parent::setUp();

		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}lifterlms_user_postmeta" );

		$this->endpoint = new LLMS_REST_Enrollments_Controller();

		$this->user_allowed = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		$this->user_forbidden = $this->factory->user->create(
			array(
				'role' => 'subscriber',
			)
		);
	}


	/**
	 * Test route registration.
	 *
	 * @since [version]
	 */
	public function test_register_routes() {

		$routes = $this->server->get_routes();
		$this->assertArrayHasKey( $this->route, $routes );
		$this->assertArrayHasKey( $this->route . '/(?P<post_id>[\d]+)', $routes );

	}

	/**
	 * Test list student enrollments
	 *
	 * @since [version]
	 */
	public function test_get_enrollments() {

		// create enrollments.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

		// Create new courses
		$course_ids = $this->factory->post->create_many( 5, array( 'post_type' => 'course' ) );

		foreach ( $course_ids as $course_id ) {
			// Enroll Student in newly created course
			llms_enroll_student( $user_id, $course_id, 'test_get_enrollments' );
		}

		$request = new WP_REST_Request( 'GET', $this->parse_route($user_id) );
		$response = $this->server->dispatch( $request );

		// Success.
		$this->assertEquals( 200, $response->get_status() );
		$res_data = $response->get_data();

		// Expect 5 enrollments
		$this->assertEquals( 5, count( $res_data ) );

		// Check enrollments post_id
		$i = 0;
		foreach ( $res_data as $enrollment ) {
			$this->assertEquals( $course_ids[$i], $res_data[$i++]['post_id'] );
		}
	}

	/**
	 * Test list student enrollments filter by post_id
	 *
	 *
	 * @since [version]
	 */
    public function test_get_enrollment_filter_post() {

		// create enrollments.
	    $user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );

	    // Create new courses
	    $course_ids = $this->factory->post->create_many( 10, array( 'post_type' => 'course' ) );

		$j = 0;
		$courses = array();
	    foreach ( $course_ids as $course_id ) {
			if ( 0 === ( $j++ % 2 ) ) {
				// Enroll Student in newly created course
				llms_enroll_student( $user_id, $course_id, 'test_filter_enrollments' );
				$courses[] = $course_id;
			}
	    }

		$request = new WP_REST_Request( 'GET', $this->parse_route($user_id) );
		$request->set_param( 'post', "$courses[1],$courses[2]" );
	    $response = $this->server->dispatch( $request );

	    // Success.
	    $this->assertEquals( 200, $response->get_status() );
	    $res_data = $response->get_data();

	    // Expect 2 enrollments
	    $this->assertEquals( 2, count( $res_data ) );

	    // Check enrollments post_id
	    $i = 0;
	    foreach ( $res_data as $enrollment ) {
			$this->assertEquals( $courses[$i+1], $res_data[$i++]['post_id'] );
		}

	}

	/**
	 * Test deleting a single enrollment.
	 *
	 * @since [version]
	 */
	public function test_delete_enrollment() {

		wp_set_current_user( $this->user_allowed );

		// create an enrollment, we need a student and a course/membership.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		// Create new course
		$course_id = $this->factory->post->create( array( 'post_type' => 'course' ) );

		// Enroll Student in newly created course/membership
		llms_enroll_student( $user_id, $course_id, 'test_delete' );

		// Delete user's enrollment
		$request = new WP_REST_Request( 'DELETE', $this->parse_route($user_id) . '/' . $course_id );
		$response = $this->server->dispatch( $request );

		// Success.
		$this->assertEquals( 204, $response->get_status() );
		// Student should not be enrolled in course
		$this->assertFalse( llms_is_user_enrolled( $user_id, $course_id ) );

	}

	private function parse_route( $student_id ) {
		return str_replace( '(?P<id>[\d]+)', $student_id, $this->route );
	}
}
