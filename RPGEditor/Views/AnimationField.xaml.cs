using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class AnimationField : UserControl
{
    public static readonly DependencyProperty AnimationIdProperty =
        DependencyProperty.Register(nameof(AnimationId), typeof(int), typeof(AnimationField),
            new FrameworkPropertyMetadata(0, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnChanged));

    public static readonly DependencyProperty AnimationsProperty =
        DependencyProperty.Register(nameof(Animations), typeof(IEnumerable<MvAnimationData>), typeof(AnimationField),
            new PropertyMetadata(null, OnChanged));

    public static readonly DependencyProperty ProjectRootPathProperty =
        DependencyProperty.Register(nameof(ProjectRootPath), typeof(string), typeof(AnimationField), new PropertyMetadata(null));

    public int AnimationId
    {
        get => (int)GetValue(AnimationIdProperty);
        set => SetValue(AnimationIdProperty, value);
    }

    public IEnumerable<MvAnimationData>? Animations
    {
        get => (IEnumerable<MvAnimationData>?)GetValue(AnimationsProperty);
        set => SetValue(AnimationsProperty, value);
    }

    public string? ProjectRootPath
    {
        get => (string?)GetValue(ProjectRootPathProperty);
        set => SetValue(ProjectRootPathProperty, value);
    }

    public AnimationField()
    {
        InitializeComponent();
    }

    private static void OnChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((AnimationField)d).UpdateDisplay();

    private void UpdateDisplay()
    {
        var animation = Animations?.FirstOrDefault(a => a.Id == AnimationId);
        DisplayText.Text = animation is not null ? $"{animation.Id}: {animation.Name}" : "(없음)";
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new AnimationPickerWindow(Animations ?? [], AnimationId, ProjectRootPath)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true)
            AnimationId = dialog.SelectedAnimationId;
    }
}
