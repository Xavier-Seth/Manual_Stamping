use std::{
    env,
    ffi::OsString,
    fs,
    io,
    net::{SocketAddr, TcpStream},
    path::{Path, PathBuf},
    process::{Child, Command, Stdio},
    sync::Mutex,
    thread,
    time::{Duration, Instant},
};

use tauri::{utils::config::WindowConfig, Manager, RunEvent, Runtime, Url, WebviewUrl};

const LARAVEL_URL: &str = "http://127.0.0.1:8000";
const LARAVEL_PORT: u16 = 8000;
const INITIAL_SERVER_WAIT: Duration = Duration::from_secs(5);
const SERVER_RETRY_INTERVAL: Duration = Duration::from_millis(250);
const SERVER_CONNECT_TIMEOUT: Duration = Duration::from_millis(200);
const CONFIG_CACHE_FILENAME: &str = "config.php";
const EVENTS_CACHE_FILENAME: &str = "events.php";
const PACKAGES_CACHE_FILENAME: &str = "packages.php";
const ROUTES_CACHE_FILENAME: &str = "routes-v7.php";
const SERVICES_CACHE_FILENAME: &str = "services.php";

struct PackagedRuntimePaths {
    laravel_app_dir: PathBuf,
    php_runtime_dir: PathBuf,
    php_exe: PathBuf,
    storage_dir: PathBuf,
    bootstrap_cache_dir: PathBuf,
    database_file: PathBuf,
}

#[derive(Default)]
struct LaravelSidecarState {
    child: Mutex<Option<Child>>,
}

#[tauri::command]
fn greet(name: &str) -> String {
    format!("Hello, {}! You've been greeted from Rust!", name)
}

fn build_php_path(php_runtime_dir: &Path) -> Result<OsString, Box<dyn std::error::Error>> {
    let existing_path = env::var_os("PATH").unwrap_or_default();
    let path_entries = std::iter::once(php_runtime_dir.to_path_buf())
        .chain(env::split_paths(&existing_path))
        .collect::<Vec<_>>();

    Ok(env::join_paths(path_entries)?)
}

fn resource_runtime_paths<R: Runtime>(
    app: &tauri::App<R>,
) -> Result<(PathBuf, PathBuf, PathBuf), Box<dyn std::error::Error>> {
    let resource_dir = app.path().resource_dir()?;
    let laravel_app_dir = resource_dir.join("runtime").join("laravel-app");
    let php_runtime_dir = resource_dir.join("runtime").join("php");
    let php_exe = php_runtime_dir.join("php.exe");

    Ok((laravel_app_dir, php_runtime_dir, php_exe))
}

fn packaged_runtime_paths<R: Runtime>(
    app: &tauri::App<R>,
) -> Result<PackagedRuntimePaths, Box<dyn std::error::Error>> {
    let (laravel_app_dir, php_runtime_dir, php_exe) = resource_runtime_paths(app)?;
    let data_root_dir = app.path().app_local_data_dir()?.join("runtime").join("laravel");

    Ok(PackagedRuntimePaths {
        laravel_app_dir,
        php_runtime_dir,
        php_exe,
        storage_dir: data_root_dir.join("storage"),
        bootstrap_cache_dir: data_root_dir.join("bootstrap").join("cache"),
        database_file: data_root_dir.join("database").join("database.sqlite"),
    })
}

fn main_window_config<R: Runtime>(
    app: &tauri::App<R>,
) -> Result<WindowConfig, Box<dyn std::error::Error>> {
    app.config()
        .app
        .windows
        .iter()
        .find(|window| window.label == "main")
        .cloned()
        .ok_or_else(|| io::Error::new(io::ErrorKind::NotFound, "Main window config not found").into())
}

fn laravel_url() -> Result<Url, Box<dyn std::error::Error>> {
    Ok(Url::parse(LARAVEL_URL)?)
}

fn copy_missing_files(source_dir: &Path, destination_dir: &Path) -> io::Result<()> {
    if !source_dir.exists() {
        return Ok(());
    }

    fs::create_dir_all(destination_dir)?;

    for entry in fs::read_dir(source_dir)? {
        let entry = entry?;
        let source_path = entry.path();
        let destination_path = destination_dir.join(entry.file_name());
        let file_type = entry.file_type()?;

        if file_type.is_dir() {
            copy_missing_files(&source_path, &destination_path)?;
        } else if file_type.is_file() && !destination_path.exists() {
            fs::copy(&source_path, &destination_path)?;
        }
    }

    Ok(())
}

fn remove_file_if_exists(path: &Path) -> io::Result<()> {
    if path.exists() {
        fs::remove_file(path)?;
    }

    Ok(())
}

fn prepare_packaged_laravel_runtime<R: Runtime>(
    app: &tauri::App<R>,
) -> Result<PackagedRuntimePaths, Box<dyn std::error::Error>> {
    let runtime_paths = packaged_runtime_paths(app)?;

    if tauri::is_dev() {
        return Ok(runtime_paths);
    }

    let framework_dir = runtime_paths.storage_dir.join("framework");

    fs::create_dir_all(runtime_paths.storage_dir.join("app"))?;
    fs::create_dir_all(framework_dir.join("cache").join("data"))?;
    fs::create_dir_all(framework_dir.join("sessions"))?;
    fs::create_dir_all(framework_dir.join("views"))?;
    fs::create_dir_all(runtime_paths.storage_dir.join("logs"))?;
    fs::create_dir_all(&runtime_paths.bootstrap_cache_dir)?;

    if let Some(database_dir) = runtime_paths.database_file.parent() {
        fs::create_dir_all(database_dir)?;
    }

    copy_missing_files(
        &runtime_paths.laravel_app_dir.join("storage").join("app"),
        &runtime_paths.storage_dir.join("app"),
    )?;

    if !runtime_paths.database_file.exists() {
        fs::copy(
            runtime_paths
                .laravel_app_dir
                .join("database")
                .join("database.sqlite"),
            &runtime_paths.database_file,
        )?;
    }

    remove_file_if_exists(&runtime_paths.bootstrap_cache_dir.join(CONFIG_CACHE_FILENAME))?;
    remove_file_if_exists(
        &runtime_paths
            .bootstrap_cache_dir
            .join(ROUTES_CACHE_FILENAME),
    )?;

    Ok(runtime_paths)
}

fn laravel_is_ready() -> bool {
    let socket_addr = SocketAddr::from(([127, 0, 0, 1], LARAVEL_PORT));
    TcpStream::connect_timeout(&socket_addr, SERVER_CONNECT_TIMEOUT).is_ok()
}

fn wait_for_laravel_server(timeout: Duration) -> bool {
    let started_at = Instant::now();

    while started_at.elapsed() < timeout {
        if laravel_is_ready() {
            return true;
        }

        thread::sleep(SERVER_RETRY_INTERVAL);
    }

    laravel_is_ready()
}

fn build_main_window<R: Runtime>(
    app: &tauri::App<R>,
    window_config: &WindowConfig,
) -> Result<(), Box<dyn std::error::Error>> {
    tauri::WebviewWindowBuilder::from_config(app, window_config)?.build()?;
    Ok(())
}

fn navigate_main_window_when_ready<R: Runtime>(app_handle: tauri::AppHandle<R>, laravel_url: Url) {
    thread::spawn(move || loop {
        if laravel_is_ready() {
            if let Some(window) = app_handle.get_webview_window("main") {
                if let Err(error) = window.navigate(laravel_url.clone()) {
                    eprintln!("Failed to navigate packaged app to Laravel: {error}");
                }
            }

            return;
        }

        thread::sleep(SERVER_RETRY_INTERVAL);
    });
}

fn spawn_packaged_laravel_server<R: Runtime>(
    app: &tauri::App<R>,
) -> Result<(), Box<dyn std::error::Error>> {
    if tauri::is_dev() {
        return Ok(());
    }

    let runtime_paths = prepare_packaged_laravel_runtime(app)?;
    let php_path = build_php_path(&runtime_paths.php_runtime_dir)?;
    let config_cache_path = runtime_paths
        .bootstrap_cache_dir
        .join(CONFIG_CACHE_FILENAME);
    let events_cache_path = runtime_paths
        .bootstrap_cache_dir
        .join(EVENTS_CACHE_FILENAME);
    let packages_cache_path = runtime_paths
        .bootstrap_cache_dir
        .join(PACKAGES_CACHE_FILENAME);
    let routes_cache_path = runtime_paths
        .bootstrap_cache_dir
        .join(ROUTES_CACHE_FILENAME);
    let services_cache_path = runtime_paths
        .bootstrap_cache_dir
        .join(SERVICES_CACHE_FILENAME);

    if !runtime_paths.php_exe.exists() {
        eprintln!(
            "Bundled PHP executable not found: {}",
            runtime_paths.php_exe.display()
        );
        return Ok(());
    }

    if !runtime_paths.laravel_app_dir.join("artisan").exists() {
        eprintln!(
            "Bundled Laravel runtime is missing artisan: {}",
            runtime_paths.laravel_app_dir.display()
        );
        return Ok(());
    }

    let child = Command::new(&runtime_paths.php_exe)
        .current_dir(&runtime_paths.laravel_app_dir)
        .env("PHPRC", &runtime_paths.php_runtime_dir)
        .env("PATH", php_path)
        .env("LARAVEL_STORAGE_PATH", &runtime_paths.storage_dir)
        .env("DB_DATABASE", &runtime_paths.database_file)
        .env("APP_CONFIG_CACHE", &config_cache_path)
        .env("APP_EVENTS_CACHE", &events_cache_path)
        .env("APP_PACKAGES_CACHE", &packages_cache_path)
        .env("APP_ROUTES_CACHE", &routes_cache_path)
        .env("APP_SERVICES_CACHE", &services_cache_path)
        .args(["artisan", "serve", "--host=127.0.0.1", "--port=8000"])
        .stdout(Stdio::null())
        .stderr(Stdio::null())
        .spawn()?;

    {
        let state = app.state::<LaravelSidecarState>();
        *state.child.lock().expect("Laravel sidecar state poisoned") = Some(child);
    }

    Ok(())
}

fn create_main_window<R: Runtime>(app: &tauri::App<R>) -> Result<(), Box<dyn std::error::Error>> {
    let mut window_config = main_window_config(app)?;

    if tauri::is_dev() {
        window_config.url = WebviewUrl::External(laravel_url()?);
        return build_main_window(app, &window_config);
    }

    let laravel_url = laravel_url()?;

    if wait_for_laravel_server(INITIAL_SERVER_WAIT) {
        window_config.url = WebviewUrl::External(laravel_url);
        return build_main_window(app, &window_config);
    }

    build_main_window(app, &window_config)?;
    navigate_main_window_when_ready(app.handle().clone(), laravel_url);

    Ok(())
}

fn stop_packaged_laravel_server<R: Runtime>(app_handle: &tauri::AppHandle<R>) {
    if let Some(state) = app_handle.try_state::<LaravelSidecarState>() {
        if let Some(mut child) = state.child.lock().expect("Laravel sidecar state poisoned").take()
        {
            let _ = child.kill();
        }
    }
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    let app = tauri::Builder::default()
        .manage(LaravelSidecarState::default())
        .plugin(tauri_plugin_opener::init())
        .setup(|app| {
            if let Err(error) = spawn_packaged_laravel_server(app) {
                eprintln!("Failed to start packaged Laravel server: {error}");
            }
            if let Err(error) = create_main_window(app) {
                eprintln!("Failed to create main window: {error}");
                return Err(error.into());
            }
            Ok(())
        })
        .invoke_handler(tauri::generate_handler![greet])
        .build(tauri::generate_context!())
        .expect("error while building tauri application");

    app.run(|app_handle, event| {
        if matches!(event, RunEvent::Exit) {
            stop_packaged_laravel_server(app_handle);
        }
    });
}
