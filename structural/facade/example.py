# Scenario: smart home automation system.
# The user taps a button on a mobile app ("Leaving Home") and expects all devices
# to configure themselves correctly. Without a Facade, the app would have to
# coordinate lighting, thermostat, security, and audio directly.

# Subsystem: controls all lighting zones
class LightingSystem:
    def turn_off_all(self) -> None:
        print("[LightingSystem] Turning off all lights...")

    def set_scene(self, scene: str) -> None:
        print(f"[LightingSystem] Setting scene: '{scene}'")


# Subsystem: controls the thermostat
class ThermostatSystem:
    def set_away_mode(self) -> None:
        print("[ThermostatSystem] Switching to away mode (18°C)...")

    def set_comfort_mode(self) -> None:
        print("[ThermostatSystem] Switching to comfort mode (22°C)...")

    def set_sleep_mode(self) -> None:
        print("[ThermostatSystem] Switching to sleep mode (19°C)...")


# Subsystem: manages the security system (sensors, alarms, cameras)
class SecuritySystem:
    def arm(self, mode: str) -> None:
        print(f"[SecuritySystem] Arming in '{mode}' mode...")

    def disarm(self) -> None:
        print("[SecuritySystem] Disarming security system...")


# Subsystem: controls audio/music throughout the house
class AudioSystem:
    def stop(self) -> None:
        print("[AudioSystem] Stopping all audio...")

    def play_scene(self, scene: str) -> None:
        print(f"[AudioSystem] Playing '{scene}' playlist...")


# The Facade — exposes high-level "scenes" that coordinate all subsystems.
# The user just says "I'm leaving" — the facade knows what that means for every device.
class SmartHomeFacade:
    def __init__(self) -> None:
        self._lighting   = LightingSystem()
        self._thermostat = ThermostatSystem()
        self._security   = SecuritySystem()
        self._audio      = AudioSystem()

        # matiz: the facade tracks current state to make transitions intelligent.
        # Knowing we're already "away" could prevent re-triggering if called twice,
        # and allows arriving_home() to restore the correct previous context.
        # State in a facade is rare but valid when transitions depend on prior mode.
        self._current_mode = "home"

    def leaving_home(self) -> None:
        print("\n[SmartHomeFacade] Activating 'leaving home' scene...")
        self._audio.stop()
        self._lighting.turn_off_all()
        self._thermostat.set_away_mode()
        self._security.arm("full")
        self._current_mode = "away"
        print("[SmartHomeFacade] Home secured. Have a safe trip.")

    def arriving_home(self) -> None:
        print("\n[SmartHomeFacade] Activating 'arriving home' scene...")
        self._security.disarm()
        self._lighting.set_scene("welcome")
        self._thermostat.set_comfort_mode()
        self._audio.play_scene("welcome")
        self._current_mode = "home"
        print("[SmartHomeFacade] Welcome home!")

    def going_to_sleep(self) -> None:
        print("\n[SmartHomeFacade] Activating 'sleep' scene...")
        self._audio.stop()
        self._lighting.set_scene("dim")
        self._thermostat.set_sleep_mode()
        self._security.arm("perimeter")
        self._current_mode = "sleep"
        print("[SmartHomeFacade] Good night.")


# Client — a mobile app with simple tap buttons.
# One tap → one facade call → all devices configured correctly.
class MobileApp:
    def __init__(self, home: SmartHomeFacade) -> None:
        self._home = home

    def tap_leaving_button(self) -> None:
        print("[MobileApp] User tapped 'Leaving Home'")
        self._home.leaving_home()

    def tap_arriving_button(self) -> None:
        print("[MobileApp] User tapped 'I'm Home'")
        self._home.arriving_home()

    def tap_sleep_button(self) -> None:
        print("[MobileApp] User tapped 'Goodnight'")
        self._home.going_to_sleep()


if __name__ == "__main__":
    app = MobileApp(SmartHomeFacade())
    app.tap_leaving_button()
    app.tap_arriving_button()
    app.tap_sleep_button()
