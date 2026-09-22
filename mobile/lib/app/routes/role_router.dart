import 'package:flutter/material.dart';
import '../../features/athlete/screens/athlete_dashboard_screen.dart';
import '../../features/coach/screens/coach_dashboard_screen.dart';

/// Routes users to appropriate dashboard screens based on their role.
class RoleRouter extends StatelessWidget {
  final String role;

  /// Creates a role-based router widget with the specified user role.
  const RoleRouter({super.key, required this.role});

  /// Builds the appropriate dashboard screen based on the user's role.
  @override
  Widget build(BuildContext context) {
    switch (role.toLowerCase()) {
      case 'coach':
        return const CoachDashboardScreen();
      case 'athlete':
        return const AthleteDashboardScreen();
      case 'super admin':
      case 'superadmin':
      case 'sports administrator':
      case 'hr & finance':
      case 'venue & tournament manager':
      case 'inventory manager':
        // Extensible fallback screen for enterprise management roles
        return Scaffold(
          appBar: AppBar(title: Text('$role Dashboard')),
          body: Center(
            child: Padding(
              padding: const EdgeInsets.all(24.0),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.admin_panel_settings, size: 64, color: Colors.blueGrey),
                  const SizedBox(height: 16),
                  Text(
                    'Welcome, $role',
                    style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Full operations console available on the Web Portal. Dedicated mobile view pending future sprint release.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.grey),
                  ),
                ],
              ),
            ),
          ),
        );
      default:
        return const AthleteDashboardScreen();
    }
  }
}
