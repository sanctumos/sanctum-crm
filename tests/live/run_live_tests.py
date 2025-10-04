#!/usr/bin/env python3
"""
Live Test Runner
Best Jobs in TA - Execute live browser and API tests
"""

import subprocess
import sys
import os
import argparse

def run_tests(test_type="all", verbose=False, headless=True):
    """
    Run live tests with specified configuration

    Args:
        test_type: "all", "browser", "api", or "workflows"
        verbose: Enable verbose output
        headless: Run browsers in headless mode
    """

    # Change to live tests directory
    os.chdir(os.path.dirname(os.path.abspath(__file__)))

    # Set environment variables for headless mode
    env = os.environ.copy()
    if headless:
        env['PLAYWRIGHT_HEADLESS'] = '1'

    # Build pytest command
    cmd = ['python', '-m', 'pytest']

    if verbose:
        cmd.append('-v')

    # Add test files based on type
    if test_type == "browser":
        cmd.append('test_browser_ui.py')
    elif test_type == "api":
        cmd.append('test_api_integration.py')
    elif test_type == "workflows":
        cmd.append('test_user_workflows.py')
    elif test_type == "all":
        cmd.extend(['test_browser_ui.py', 'test_user_workflows.py', 'test_api_integration.py'])

    # Add coverage
    cmd.extend(['--cov=../../public', '--cov-report=html', '--cov-report=term'])

    print(f"Running live tests: {test_type}")
    print(f"Command: {' '.join(cmd)}")

    try:
        result = subprocess.run(cmd, env=env, cwd=os.getcwd())
        return result.returncode == 0
    except KeyboardInterrupt:
        print("\nTests interrupted by user")
        return False
    except Exception as e:
        print(f"Error running tests: {e}")
        return False

def main():
    parser = argparse.ArgumentParser(description='Run live tests for Sanctum CRM')
    parser.add_argument('--type', choices=['all', 'browser', 'api', 'workflows'],
                       default='all', help='Type of tests to run')
    parser.add_argument('--verbose', '-v', action='store_true',
                       help='Enable verbose output')
    parser.add_argument('--headed', action='store_true',
                       help='Run browsers in headed mode (visible)')

    args = parser.parse_args()

    success = run_tests(
        test_type=args.type,
        verbose=args.verbose,
        headless=not args.headed
    )

    if success:
        print("✅ All live tests passed!")
        sys.exit(0)
    else:
        print("❌ Some live tests failed!")
        sys.exit(1)

if __name__ == "__main__":
    main()
