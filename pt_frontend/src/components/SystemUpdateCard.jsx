import { useState, useEffect } from 'react'
import { updateAPI } from '../services/api'
import { useLanguage } from '../contexts/LanguageContext'
import { toast } from '../utils/toast'

/**
 * 系统更新卡片（仅管理员）：检查更新源（GitHub Releases）并一键安装。
 * 更新由后端 update/apply.php 执行：content/ 用户数据与本地配置永不被覆盖。
 */
export default function SystemUpdateCard() {
    const { lang } = useLanguage()
    const [info, setInfo] = useState(null)
    const [checking, setChecking] = useState(false)
    const [applying, setApplying] = useState(false)

    useEffect(() => {
        check()
    }, [])

    const check = async () => {
        setChecking(true)
        try {
            const res = await updateAPI.check()
            if (res.success) {
                setInfo(res.data)
            } else {
                setInfo(null)
                toast(lang('updateCheckFailed') + ': ' + res.message, 'error')
            }
        } catch (err) {
            setInfo(null)
            toast(lang('updateCheckFailed') + ': ' + err.message, 'error')
        } finally {
            setChecking(false)
        }
    }

    const apply = async () => {
        if (!window.confirm(lang('updateConfirm'))) return
        setApplying(true)
        try {
            const res = await updateAPI.apply()
            if (res.success) {
                if (res.data?.errors?.length) {
                    toast(lang('updateSuccess') + ' (' + res.data.errors.length + ')', 'error')
                } else {
                    toast(lang('updateSuccess'), 'success')
                }
            } else {
                toast(lang('error') + ': ' + res.message, 'error')
            }
        } catch (err) {
            toast(lang('error') + ': ' + err.message, 'error')
        } finally {
            setApplying(false)
            await check()
        }
    }

    return (
        <div className="card shadow-sm mt-4">
            <div className="card-body">
                <div className="d-flex justify-content-between align-items-center mb-3">
                    <h5 className="mb-0">
                        <i className="bi bi-arrow-repeat me-2"></i>
                        {lang('systemUpdate')}
                    </h5>
                    <button
                        className="btn btn-outline-secondary btn-sm"
                        onClick={check}
                        disabled={checking || applying}
                    >
                        {checking ? (
                            <>
                                <span className="spinner-border spinner-border-sm me-2"></span>
                                {lang('checking')}
                            </>
                        ) : (
                            <>
                                <i className="bi bi-arrow-clockwise me-1"></i>
                                {lang('checkUpdate')}
                            </>
                        )}
                    </button>
                </div>

                {info && (
                    <div>
                        <div className="d-flex flex-wrap align-items-center gap-3 mb-2">
                            <span>
                                {lang('currentVersion')}: <strong>{info.current_version}</strong>
                            </span>
                            <span>
                                {lang('latestVersion')}: <strong>{info.latest_version}</strong>
                            </span>
                            {info.update_available ? (
                                <span className="badge text-bg-warning">{lang('updateAvailable')}</span>
                            ) : (
                                <span className="badge text-bg-success">{lang('upToDate')}</span>
                            )}
                        </div>

                        {info.notes && (
                            <details className="text-muted small mb-2">
                                <summary>{lang('releaseNotes')}</summary>
                                <pre className="mb-0 text-wrap" style={{ whiteSpace: 'pre-wrap' }}>
                                    {info.notes}
                                </pre>
                            </details>
                        )}

                        {info.update_available && (
                            <button className="btn btn-primary" onClick={apply} disabled={applying}>
                                {applying ? (
                                    <>
                                        <span className="spinner-border spinner-border-sm me-2"></span>
                                        {lang('installing')}
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-download me-1"></i>
                                        {lang('installUpdate')}
                                    </>
                                )}
                            </button>
                        )}
                    </div>
                )}
            </div>
        </div>
    )
}
