using System.Collections.Generic;
using System.IO;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;
using RPGEditor.ViewModels;

namespace RPGEditor.Views;

public partial class EnemyEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => FaceField.ProjectRootPath;
        set
        {
            FaceField.ProjectRootPath = value;
            AttackAnimationField.ProjectRootPath = value;
        }
    }

    /// <summary>공격 애니메이션 선택 다이얼로그에 사용되는 Animations.json 데이터.</summary>
    public IEnumerable<MvAnimationData>? Animations
    {
        get => AttackAnimationField.Animations;
        set => AttackAnimationField.Animations = value;
    }

    public EnemyEditorView()
    {
        InitializeComponent();
    }

    private void BrowseImageButton_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is not DatabaseListViewModel<Enemy> vm || vm.Selected is not { } enemy)
            return;
        if (string.IsNullOrEmpty(ProjectRootPath))
            return;

        if (enemy.ImageType == EnemyImageType.DragonBones)
        {
            var folder = Path.Combine(ProjectRootPath, "img", "dragonbones");
            var dialog = new DragonBonesPickerWindow(folder, enemy.Image)
            {
                Owner = Application.Current?.MainWindow,
            };
            if (dialog.ShowDialog() == true && dialog.SelectedArmatureName is { } name)
                enemy.Image = name;
        }
        else
        {
            var folder = Path.Combine(ProjectRootPath, "img", "sv_enemies");
            var dialog = new ImageFilePickerWindow(folder, enemy.Image, "SV 배틀러 이미지 선택")
            {
                Owner = Application.Current?.MainWindow,
            };
            if (dialog.ShowDialog() == true && dialog.SelectedImageName is { } name)
                enemy.Image = name;
        }
    }
}
